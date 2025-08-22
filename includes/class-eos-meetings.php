<?php
/**
 * EOS L10 Meetings Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Meetings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_eos_save_meeting', array($this, 'ajax_save_meeting'));
        add_action('wp_ajax_eos_start_meeting', array($this, 'ajax_start_meeting'));
        add_action('wp_ajax_eos_complete_meeting', array($this, 'ajax_complete_meeting'));
        add_action('wp_ajax_eos_create_google_meet', array($this, 'ajax_create_google_meet'));
        add_action('wp_ajax_eos_save_agenda_item', array($this, 'ajax_save_agenda_item'));
        
        // Schedule meeting reminders
        add_action('eos_daily_meeting_reminder', array($this, 'send_meeting_reminders'));
    }
    
    /**
     * Get meetings with optional filtering
     */
    public static function get_meetings($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'upcoming' => false,
            'past' => false,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'meeting_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = EOS_Database::get_table_name('meetings');
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if ($args['upcoming']) {
            $where_clauses[] = "meeting_date >= %s";
            $where_values[] = current_time('mysql');
        }
        
        if ($args['past']) {
            $where_clauses[] = "meeting_date < %s";
            $where_values[] = current_time('mysql');
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_sql = sprintf('ORDER BY %s %s', 
            sanitize_sql_orderby($args['orderby']), 
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $meetings = $wpdb->get_results($sql, ARRAY_A);
        
        // Enhance meeting data
        foreach ($meetings as &$meeting) {
            $meeting = self::enhance_meeting_data($meeting);
        }
        
        return $meetings;
    }
    
    /**
     * Get upcoming meetings
     */
    public static function get_upcoming($limit = 5) {
        return self::get_meetings(array(
            'upcoming' => true,
            'status' => 'scheduled',
            'limit' => $limit,
            'order' => 'ASC'
        ));
    }
    
    /**
     * Get a single meeting by ID
     */
    public static function get_meeting($id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('meetings');
        $meeting = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($meeting) {
            $meeting = self::enhance_meeting_data($meeting);
        }
        
        return $meeting;
    }
    
    /**
     * Save a meeting (create or update)
     */
    public static function save_meeting($data) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('meetings');
        
        // Sanitize and validate data
        $meeting_data = array(
            'title' => sanitize_text_field($data['title']),
            'meeting_date' => sanitize_text_field($data['meeting_date']),
            'duration' => intval($data['duration']) ?: 90,
            'attendees' => wp_kses_post($data['attendees']),
            'status' => in_array($data['status'], array('scheduled', 'in-progress', 'completed', 'cancelled')) ? $data['status'] : 'scheduled'
        );
        
        // Optional fields for L10 agenda
        $agenda_fields = array(
            'agenda_notes', 'segue_notes', 'scorecard_notes', 'rock_review_notes',
            'headlines_notes', 'todo_review_notes', 'ids_notes', 'conclusion_notes',
            'action_items', 'google_meet_link'
        );
        
        foreach ($agenda_fields as $field) {
            if (isset($data[$field])) {
                $meeting_data[$field] = wp_kses_post($data[$field]);
            }
        }
        
        // Validate required fields
        if (empty($meeting_data['title'])) {
            return array('success' => false, 'message' => __('Meeting title is required.', 'eos-manager'));
        }
        
        if (empty($meeting_data['meeting_date'])) {
            return array('success' => false, 'message' => __('Meeting date is required.', 'eos-manager'));
        }
        
        // Validate datetime
        if (!self::is_valid_datetime($meeting_data['meeting_date'])) {
            return array('success' => false, 'message' => __('Invalid meeting date format.', 'eos-manager'));
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing meeting
            $meeting_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table,
                $meeting_data,
                array('id' => intval($data['id'])),
                null,
                array('%d')
            );
            
            if ($result !== false) {
                $meeting_id = intval($data['id']);
                self::log_activity('updated', $meeting_id, $meeting_data['title']);
                return array('success' => true, 'message' => __('Meeting updated successfully.', 'eos-manager'), 'meeting_id' => $meeting_id);
            }
        } else {
            // Create new meeting
            $meeting_data['created_at'] = current_time('mysql');
            $meeting_data['created_by'] = get_current_user_id();
            
            // Auto-generate Google Meet link if requested
            if (!empty($data['create_google_meet'])) {
                $meeting_data['google_meet_link'] = self::create_google_meet_link($meeting_data);
            }
            
            $result = $wpdb->insert($table, $meeting_data);
            
            if ($result !== false) {
                $meeting_id = $wpdb->insert_id;
                self::log_activity('created', $meeting_id, $meeting_data['title']);
                
                // Schedule reminder if it's a future meeting
                if (strtotime($meeting_data['meeting_date']) > current_time('timestamp')) {
                    self::schedule_meeting_reminder($meeting_id);
                }
                
                return array('success' => true, 'message' => __('Meeting created successfully.', 'eos-manager'), 'meeting_id' => $meeting_id);
            }
        }
        
        return array('success' => false, 'message' => __('Failed to save meeting. Please try again.', 'eos-manager'));
    }
    
    /**
     * Start a meeting (change status to in-progress)
     */
    public static function start_meeting($meeting_id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('meetings');
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'in-progress',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $meeting_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $meeting = self::get_meeting($meeting_id);
            self::log_activity('started', $meeting_id, $meeting['title']);
            
            return array('success' => true, 'message' => __('Meeting started successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to start meeting.', 'eos-manager'));
    }
    
    /**
     * Complete a meeting
     */
    public static function complete_meeting($meeting_id, $action_items = '') {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('meetings');
        
        $update_data = array(
            'status' => 'completed',
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($action_items)) {
            $update_data['action_items'] = wp_kses_post($action_items);
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $meeting_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $meeting = self::get_meeting($meeting_id);
            self::log_activity('completed', $meeting_id, $meeting['title']);
            
            // Create to-dos from action items
            if (!empty($action_items)) {
                self::create_todos_from_action_items($meeting_id, $action_items);
            }
            
            return array('success' => true, 'message' => __('Meeting completed successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to complete meeting.', 'eos-manager'));
    }
    
    /**
     * Create Google Meet link (placeholder - would integrate with Google Calendar API)
     */
    public static function create_google_meet_link($meeting_data) {
        // In a real implementation, this would integrate with Google Calendar API
        // For now, we'll return a placeholder link
        
        $meet_id = 'eos-' . uniqid();
        return 'https://meet.google.com/' . $meet_id;
    }
    
    /**
     * Get L10 agenda structure
     */
    public static function get_l10_agenda_structure() {
        return array(
            array(
                'section' => 'segue',
                'title' => __('Segue', 'eos-manager'),
                'duration' => 5,
                'description' => __('Good news sharing (personal and business)', 'eos-manager')
            ),
            array(
                'section' => 'scorecard',
                'title' => __('Scorecard Review', 'eos-manager'),
                'duration' => 5,
                'description' => __('Review weekly numbers', 'eos-manager')
            ),
            array(
                'section' => 'rock_review',
                'title' => __('Rock Review', 'eos-manager'),
                'duration' => 5,
                'description' => __('90-day priority progress', 'eos-manager')
            ),
            array(
                'section' => 'headlines',
                'title' => __('Customer/Employee Headlines', 'eos-manager'),
                'duration' => 5,
                'description' => __('Important updates and news', 'eos-manager')
            ),
            array(
                'section' => 'todo_review',
                'title' => __('To-Do List Review', 'eos-manager'),
                'duration' => 5,
                'description' => __('Review previous action items', 'eos-manager')
            ),
            array(
                'section' => 'ids',
                'title' => __('IDS - Issues, Discuss, Solve', 'eos-manager'),
                'duration' => 60,
                'description' => __('Identify, discuss, and solve issues', 'eos-manager')
            ),
            array(
                'section' => 'conclude',
                'title' => __('Conclude', 'eos-manager'),
                'duration' => 5,
                'description' => __('Action items and next meeting', 'eos-manager')
            )
        );
    }
    
    /**
     * Save agenda item notes
     */
    public static function save_agenda_item($meeting_id, $section, $notes) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('meetings');
        $field_name = $section . '_notes';
        
        $result = $wpdb->update(
            $table,
            array(
                $field_name => wp_kses_post($notes),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $meeting_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get meeting statistics
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('meetings');
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scheduled' AND meeting_date >= NOW() THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
                AVG(duration) as avg_duration
            FROM $table
        ", ARRAY_A);
        
        return array(
            'total' => intval($stats['total']),
            'upcoming' => intval($stats['upcoming']),
            'completed' => intval($stats['completed']),
            'in_progress' => intval($stats['in_progress']),
            'avg_duration' => round(floatval($stats['avg_duration']), 0)
        );
    }
    
    /**
     * Create to-dos from action items
     */
    private static function create_todos_from_action_items($meeting_id, $action_items) {
        $lines = explode("\n", $action_items);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse action item format: "Task - Assignee (Due Date)"
            if (preg_match('/^(.+?)\s*-\s*(.+?)(?:\s*\((.+?)\))?$/', $line, $matches)) {
                $task = trim($matches[1]);
                $assignee = trim($matches[2]);
                $due_date = isset($matches[3]) ? trim($matches[3]) : '';
                
                EOS_Todos::create_todo(array(
                    'title' => $task,
                    'assignee' => $assignee,
                    'due_date' => $due_date ? date('Y-m-d', strtotime($due_date)) : '',
                    'meeting_id' => $meeting_id
                ));
            }
        }
    }
    
    /**
     * Schedule meeting reminder
     */
    private static function schedule_meeting_reminder($meeting_id) {
        $meeting = self::get_meeting($meeting_id);
        
        if (!$meeting) return;
        
        $meeting_time = strtotime($meeting['meeting_date']);
        $reminder_time = $meeting_time - (24 * 60 * 60); // 24 hours before
        
        if ($reminder_time > current_time('timestamp')) {
            wp_schedule_single_event($reminder_time, 'eos_meeting_reminder', array($meeting_id));
        }
    }
    
    /**
     * Send meeting reminders
     */
    public function send_meeting_reminders() {
        $upcoming_meetings = self::get_upcoming(10);
        
        foreach ($upcoming_meetings as $meeting) {
            $meeting_date = new DateTime($meeting['meeting_date']);
            $tomorrow = new DateTime('+1 day');
            
            // Send reminder for meetings happening tomorrow
            if ($meeting_date->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
                self::send_meeting_reminder($meeting);
            }
        }
    }
    
    /**
     * Send individual meeting reminder
     */
    private static function send_meeting_reminder($meeting) {
        $attendees = explode(',', $meeting['attendees']);
        $subject = sprintf(__('Reminder: %s tomorrow', 'eos-manager'), $meeting['title']);
        $meeting_date = new DateTime($meeting['meeting_date']);
        
        $message = sprintf(
            __("Hello!\n\nThis is a reminder that you have the following L10 meeting scheduled for tomorrow:\n\n%s\nDate: %s\nTime: %s\n\n", 'eos-manager'),
            $meeting['title'],
            $meeting_date->format(get_option('date_format')),
            $meeting_date->format(get_option('time_format'))
        );
        
        if (!empty($meeting['google_meet_link'])) {
            $message .= sprintf(__("Google Meet Link: %s\n\n", 'eos-manager'), $meeting['google_meet_link']);
        }
        
        $message .= __("Please be prepared with your updates for the scorecard and rock review.\n\nSee you there!", 'eos-manager');
        
        foreach ($attendees as $attendee) {
            $attendee = trim($attendee);
            if (is_email($attendee)) {
                wp_mail($attendee, $subject, $message);
            }
        }
    }
    
    /**
     * Enhance meeting data with calculated fields
     */
    private static function enhance_meeting_data($meeting) {
        if (!$meeting) return $meeting;
        
        $meeting_date = new DateTime($meeting['meeting_date']);
        $now = new DateTime();
        
        // Format dates
        $meeting['meeting_date_formatted'] = date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'), 
            strtotime($meeting['meeting_date'])
        );
        
        $meeting['created_at_formatted'] = date_i18n(
            get_option('date_format'), 
            strtotime($meeting['created_at'])
        );
        
        // Calculate time until meeting
        if ($meeting_date > $now) {
            $diff = $now->diff($meeting_date);
            $meeting['time_until'] = self::format_time_difference($diff);
        } else {
            $diff = $meeting_date->diff($now);
            $meeting['time_since'] = self::format_time_difference($diff);
        }
        
        // Parse attendees
        $meeting['attendees_array'] = array_map('trim', explode(',', $meeting['attendees']));
        $meeting['attendee_count'] = count($meeting['attendees_array']);
        
        return $meeting;
    }
    
    /**
     * Format time difference for display
     */
    private static function format_time_difference($diff) {
        if ($diff->days > 0) {
            return sprintf(_n('%d day', '%d days', $diff->days, 'eos-manager'), $diff->days);
        } elseif ($diff->h > 0) {
            return sprintf(_n('%d hour', '%d hours', $diff->h, 'eos-manager'), $diff->h);
        } else {
            return sprintf(_n('%d minute', '%d minutes', $diff->i, 'eos-manager'), $diff->i);
        }
    }
    
    /**
     * Validate datetime format
     */
    private static function is_valid_datetime($datetime) {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }
    
    /**
     * Log meeting activity
     */
    private static function log_activity($action, $meeting_id, $meeting_title, $meta = array()) {
        $activity_data = array(
            'type' => 'meetings',
            'action' => $action,
            'item_id' => $meeting_id,
            'title' => sprintf(__('Meeting %s: %s', 'eos-manager'), $action, $meeting_title),
            'meta' => $meta,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        switch ($action) {
            case 'created':
                $activity_data['description'] = sprintf(__('L10 meeting "%s" was scheduled', 'eos-manager'), $meeting_title);
                $activity_data['icon'] = '📅';
                break;
            case 'started':
                $activity_data['description'] = sprintf(__('L10 meeting "%s" was started', 'eos-manager'), $meeting_title);
                $activity_data['icon'] = '▶️';
                break;
            case 'completed':
                $activity_data['description'] = sprintf(__('L10 meeting "%s" was completed', 'eos-manager'), $meeting_title);
                $activity_data['icon'] = '✅';
                break;
            case 'updated':
                $activity_data['description'] = sprintf(__('L10 meeting "%s" was updated', 'eos-manager'), $meeting_title);
                $activity_data['icon'] = '✏️';
                break;
        }
        
        do_action('eos_log_activity', $activity_data);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_save_meeting() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $meeting_data = $_POST['meeting_data'];
        $result = self::save_meeting($meeting_data);
        
        wp_send_json($result);
    }
    
    public function ajax_start_meeting() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $meeting_id = intval($_POST['meeting_id']);
        $result = self::start_meeting($meeting_id);
        
        wp_send_json($result);
    }
    
    public function ajax_complete_meeting() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $meeting_id = intval($_POST['meeting_id']);
        $action_items = sanitize_textarea_field($_POST['action_items']);
        
        $result = self::complete_meeting($meeting_id, $action_items);
        wp_send_json($result);
    }
    
    public function ajax_save_agenda_item() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $meeting_id = intval($_POST['meeting_id']);
        $section = sanitize_key($_POST['section']);
        $notes = wp_kses_post($_POST['notes']);
        
        $result = self::save_agenda_item($meeting_id, $section, $notes);
        wp_send_json(array('success' => $result));
    }
    
    public function ajax_create_google_meet() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $meeting_data = $_POST['meeting_data'];
        $meet_link = self::create_google_meet_link($meeting_data);
        
        wp_send_json(array('success' => true, 'meet_link' => $meet_link));
    }
}
