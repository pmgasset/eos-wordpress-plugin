<?php
/**
 * EOS People Analyzer Class - GWC Assessment
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_People {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_eos_save_person', array($this, 'ajax_save_person'));
        add_action('wp_ajax_eos_update_gwc', array($this, 'ajax_update_gwc'));
        add_action('wp_ajax_eos_delete_person', array($this, 'ajax_delete_person'));
        add_action('wp_ajax_eos_assess_person', array($this, 'ajax_assess_person'));
    }
    
    /**
     * Get all people with optional filtering
     */
    public static function get_people($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'is_active' => true,
            'department' => '',
            'status' => '',
            'seat' => '',
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = EOS_Database::get_table_name('people');
        
        $where_clauses = array();
        $where_values = array();
        
        if ($args['is_active'] !== null) {
            $where_clauses[] = "is_active = %d";
            $where_values[] = $args['is_active'] ? 1 : 0;
        }
        
        if (!empty($args['department'])) {
            $where_clauses[] = "department = %s";
            $where_values[] = $args['department'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['seat'])) {
            $where_clauses[] = "seat = %s";
            $where_values[] = $args['seat'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_sql = sprintf('ORDER BY %s %s', 
            sanitize_sql_orderby($args['orderby']), 
            $args['order'] === 'DESC' ? 'DESC' : 'ASC'
        );
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $people = $wpdb->get_results($sql, ARRAY_A);
        
        // Enhance people data
        foreach ($people as &$person) {
            $person = self::enhance_person_data($person);
        }
        
        return $people;
    }
    
    /**
     * Get a single person by ID
     */
    public static function get_person($id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        $person = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($person) {
            $person = self::enhance_person_data($person);
        }
        
        return $person;
    }
    
    /**
     * Save a person (create or update)
     */
    public static function save_person($data) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        
        // Sanitize and validate data
        $person_data = array(
            'name' => sanitize_text_field($data['name']),
            'role' => sanitize_text_field($data['role']),
            'seat' => sanitize_text_field($data['seat']),
            'department' => sanitize_text_field($data['department']),
            'email' => sanitize_email($data['email']),
            'get_it' => !empty($data['get_it']),
            'want_it' => !empty($data['want_it']),
            'capacity' => !empty($data['capacity']),
            'is_active' => !empty($data['is_active']),
            'notes' => wp_kses_post($data['notes'])
        );
        
        if (!empty($data['start_date'])) {
            $person_data['start_date'] = sanitize_text_field($data['start_date']);
        }
        
        // Calculate status based on GWC
        $person_data['status'] = self::calculate_status(
            $person_data['get_it'],
            $person_data['want_it'],
            $person_data['capacity']
        );
        
        // Validate required fields
        if (empty($person_data['name'])) {
            return array('success' => false, 'message' => __('Person name is required.', 'eos-manager'));
        }
        
        if (!empty($person_data['email']) && !is_email($person_data['email'])) {
            return array('success' => false, 'message' => __('Invalid email address.', 'eos-manager'));
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing person
            $person_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table,
                $person_data,
                array('id' => intval($data['id'])),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $person_id = intval($data['id']);
                self::log_activity('updated', $person_id, $person_data['name']);
                return array('success' => true, 'message' => __('Person updated successfully.', 'eos-manager'), 'person_id' => $person_id);
            }
        } else {
            // Create new person
            $person_data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $table,
                $person_data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $person_id = $wpdb->insert_id;
                self::log_activity('created', $person_id, $person_data['name']);
                return array('success' => true, 'message' => __('Person added successfully.', 'eos-manager'), 'person_id' => $person_id);
            }
        }
        
        return array('success' => false, 'message' => __('Failed to save person. Please try again.', 'eos-manager'));
    }
    
    /**
     * Update GWC assessment for a person
     */
    public static function update_gwc($person_id, $get_it, $want_it, $capacity) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        
        $status = self::calculate_status($get_it, $want_it, $capacity);
        
        $result = $wpdb->update(
            $table,
            array(
                'get_it' => $get_it ? 1 : 0,
                'want_it' => $want_it ? 1 : 0,
                'capacity' => $capacity ? 1 : 0,
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $person_id),
            array('%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $person = self::get_person($person_id);
            self::log_activity('gwc_updated', $person_id, $person['name'], array(
                'get_it' => $get_it,
                'want_it' => $want_it,
                'capacity' => $capacity,
                'status' => $status
            ));
            
            return array('success' => true, 'status' => $status, 'message' => __('GWC assessment updated.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to update GWC assessment.', 'eos-manager'));
    }
    
    /**
     * Delete a person
     */
    public static function delete_person($person_id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        $person = self::get_person($person_id);
        
        if (!$person) {
            return array('success' => false, 'message' => __('Person not found.', 'eos-manager'));
        }
        
        $result = $wpdb->delete(
            $table,
            array('id' => $person_id),
            array('%d')
        );
        
        if ($result !== false) {
            self::log_activity('deleted', $person_id, $person['name']);
            return array('success' => true, 'message' => __('Person removed successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to delete person.', 'eos-manager'));
    }
    
    /**
     * Get people statistics for dashboard
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'right-person-right-seat' AND is_active = 1 THEN 1 ELSE 0 END) as right_seat,
                SUM(CASE WHEN status != 'right-person-right-seat' AND is_active = 1 THEN 1 ELSE 0 END) as need_review,
                SUM(CASE WHEN get_it = 1 AND want_it = 1 AND capacity = 1 AND is_active = 1 THEN 1 ELSE 0 END) as full_gwc
            FROM $table
        ", ARRAY_A);
        
        return array(
            'total' => intval($stats['total']),
            'active' => intval($stats['active']),
            'right_seat' => intval($stats['right_seat']),
            'need_review' => intval($stats['need_review']),
            'full_gwc' => intval($stats['full_gwc'])
        );
    }
    
    /**
     * Get GWC matrix data for visualization
     */
    public static function get_gwc_matrix() {
        $people = self::get_people(array('is_active' => true));
        
        $matrix = array(
            'right-person-right-seat' => array(),
            'right-person-wrong-seat' => array(),
            'wrong-person-right-seat' => array(),
            'wrong-person-wrong-seat' => array()
        );
        
        foreach ($people as $person) {
            $matrix[$person['status']][] = $person;
        }
        
        return $matrix;
    }
    
    /**
     * Get people by department
     */
    public static function get_people_by_department() {
        $people = self::get_people(array('is_active' => true));
        $departments = array();
        
        foreach ($people as $person) {
            $dept = !empty($person['department']) ? $person['department'] : 'No Department';
            if (!isset($departments[$dept])) {
                $departments[$dept] = array();
            }
            $departments[$dept][] = $person;
        }
        
        return $departments;
    }
    
    /**
     * Get available seats/roles
     */
    public static function get_available_seats() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        
        $seats = $wpdb->get_col("
            SELECT DISTINCT seat 
            FROM $table 
            WHERE seat IS NOT NULL AND seat != '' AND is_active = 1
            ORDER BY seat ASC
        ");
        
        return $seats;
    }
    
    /**
     * Get available departments
     */
    public static function get_departments() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('people');
        
        $departments = $wpdb->get_col("
            SELECT DISTINCT department 
            FROM $table 
            WHERE department IS NOT NULL AND department != '' AND is_active = 1
            ORDER BY department ASC
        ");
        
        // Add default departments if none exist
        if (empty($departments)) {
            $departments = array(
                'Leadership',
                'Sales',
                'Marketing',
                'Operations',
                'Finance',
                'Human Resources',
                'Technology',
                'Customer Service'
            );
        }
        
        return $departments;
    }
    
    /**
     * Generate GWC assessment questions
     */
    public static function get_gwc_questions() {
        return array(
            'get_it' => array(
                'title' => __('Get It', 'eos-manager'),
                'description' => __('Does this person truly understand their role, the culture, the company systems, and how to be successful in the seat?', 'eos-manager'),
                'questions' => array(
                    __('Understands their role and responsibilities', 'eos-manager'),
                    __('Comprehends company culture and values', 'eos-manager'),
                    __('Grasps the systems and processes', 'eos-manager'),
                    __('Knows what success looks like in their position', 'eos-manager'),
                    __('Learns quickly and applies knowledge effectively', 'eos-manager')
                )
            ),
            'want_it' => array(
                'title' => __('Want It', 'eos-manager'),
                'description' => __('Does this person genuinely like their role and want to do it based on fair compensation?', 'eos-manager'),
                'questions' => array(
                    __('Shows genuine enthusiasm for their work', 'eos-manager'),
                    __('Takes initiative and ownership', 'eos-manager'),
                    __('Demonstrates passion for the role', 'eos-manager'),
                    __('Goes above and beyond when needed', 'eos-manager'),
                    __('Would choose this role again given the opportunity', 'eos-manager')
                )
            ),
            'capacity' => array(
                'title' => __('Capacity', 'eos-manager'),
                'description' => __('Does this person have the time, mental, physical, and emotional capacity to do their job?', 'eos-manager'),
                'questions' => array(
                    __('Has the mental bandwidth for their responsibilities', 'eos-manager'),
                    __('Demonstrates emotional intelligence and stability', 'eos-manager'),
                    __('Physically able to handle job requirements', 'eos-manager'),
                    __('Has sufficient time to complete tasks effectively', 'eos-manager'),
                    __('Can handle stress and pressure appropriately', 'eos-manager')
                )
            )
        );
    }
    
    /**
     * Calculate status based on GWC
     */
    private static function calculate_status($get_it, $want_it, $capacity) {
        $right_person = $get_it && $want_it;
        $right_seat = $capacity;
        
        if ($right_person && $right_seat) {
            return 'right-person-right-seat';
        } elseif ($right_person && !$right_seat) {
            return 'right-person-wrong-seat';
        } elseif (!$right_person && $right_seat) {
            return 'wrong-person-right-seat';
        } else {
            return 'wrong-person-wrong-seat';
        }
    }
    
    /**
     * Enhance person data with calculated fields
     */
    private static function enhance_person_data($person) {
        if (!$person) return $person;
        
        // Format dates
        $person['created_at_formatted'] = date_i18n(get_option('date_format'), strtotime($person['created_at']));
        $person['updated_at_formatted'] = date_i18n(get_option('date_format'), strtotime($person['updated_at']));
        
        if ($person['start_date']) {
            $person['start_date_formatted'] = date_i18n(get_option('date_format'), strtotime($person['start_date']));
            
            // Calculate tenure
            $start_date = new DateTime($person['start_date']);
            $now = new DateTime();
            $tenure = $start_date->diff($now);
            $person['tenure_years'] = $tenure->y;
            $person['tenure_months'] = $tenure->m;
            $person['tenure_formatted'] = self::format_tenure($tenure);
        }
        
        // GWC status labels
        $status_labels = array(
            'right-person-right-seat' => __('Right Person, Right Seat', 'eos-manager'),
            'right-person-wrong-seat' => __('Right Person, Wrong Seat', 'eos-manager'),
            'wrong-person-right-seat' => __('Wrong Person, Right Seat', 'eos-manager'),
            'wrong-person-wrong-seat' => __('Wrong Person, Wrong Seat', 'eos-manager')
        );
        $person['status_label'] = $status_labels[$person['status']] ?? $person['status'];
        
        // GWC indicators
        $person['gwc_score'] = intval($person['get_it']) + intval($person['want_it']) + intval($person['capacity']);
        $person['gwc_percentage'] = round(($person['gwc_score'] / 3) * 100, 0);
        
        // Action needed flag
        $person['action_needed'] = $person['status'] !== 'right-person-right-seat';
        
        return $person;
    }
    
    /**
     * Format tenure for display
     */
    private static function format_tenure($tenure) {
        if ($tenure->y > 0) {
            if ($tenure->m > 0) {
                return sprintf(__('%d years, %d months', 'eos-manager'), $tenure->y, $tenure->m);
            } else {
                return sprintf(_n('%d year', '%d years', $tenure->y, 'eos-manager'), $tenure->y);
            }
        } elseif ($tenure->m > 0) {
            return sprintf(_n('%d month', '%d months', $tenure->m, 'eos-manager'), $tenure->m);
        } else {
            return __('Less than a month', 'eos-manager');
        }
    }
    
    /**
     * Log people activity
     */
    private static function log_activity($action, $person_id, $person_name, $meta = array()) {
        $activity_data = array(
            'type' => 'people',
            'action' => $action,
            'item_id' => $person_id,
            'title' => sprintf(__('People %s: %s', 'eos-manager'), $action, $person_name),
            'meta' => $meta,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        switch ($action) {
            case 'created':
                $activity_data['description'] = sprintf(__('New team member "%s" was added', 'eos-manager'), $person_name);
                $activity_data['icon'] = '👥';
                break;
            case 'updated':
                $activity_data['description'] = sprintf(__'Team member "%s" was updated', 'eos-manager'), $person_name);
                $activity_data['icon'] = '✏️';
                break;
            case 'gwc_updated':
                $gwc_status = $meta['get_it'] && $meta['want_it'] && $meta['capacity'] ? 'passes' : 'needs attention';
                $activity_data['description'] = sprintf(__('GWC assessment for "%s" updated - %s', 'eos-manager'), $person_name, $gwc_status);
                $activity_data['icon'] = '📊';
                break;
            case 'deleted':
                $activity_data['description'] = sprintf(__('Team member "%s" was removed', 'eos-manager'), $person_name);
                $activity_data['icon'] = '🗑️';
                break;
        }
        
        do_action('eos_log_activity', $activity_data);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_save_person() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $person_data = $_POST['person_data'];
        $result = self::save_person($person_data);
        
        wp_send_json($result);
    }
    
    public function ajax_update_gwc() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $person_id = intval($_POST['person_id']);
        $get_it = !empty($_POST['get_it']);
        $want_it = !empty($_POST['want_it']);
        $capacity = !empty($_POST['capacity']);
        
        $result = self::update_gwc($person_id, $get_it, $want_it, $capacity);
        wp_send_json($result);
    }
    
    public function ajax_delete_person() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $person_id = intval($_POST['person_id']);
        $result = self::delete_person($person_id);
        
        wp_send_json($result);
    }
    
    public function ajax_assess_person() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $person_id = intval($_POST['person_id']);
        $assessment_data = $_POST['assessment_data'];
        
        // Process assessment scores and update GWC
        $get_it = intval($assessment_data['get_it_score']) >= 4;
        $want_it = intval($assessment_data['want_it_score']) >= 4;
        $capacity = intval($assessment_data['capacity_score']) >= 4;
        
        $result = self::update_gwc($person_id, $get_it, $want_it, $capacity);
        
        if ($result['success']) {
            $result['assessment'] = array(
                'get_it_score' => $assessment_data['get_it_score'],
                'want_it_score' => $assessment_data['want_it_score'],
                'capacity_score' => $assessment_data['capacity_score'],
                'notes' => sanitize_textarea_field($assessment_data['notes'])
            );
        }
        
        wp_send_json($result);
    }
}

?>