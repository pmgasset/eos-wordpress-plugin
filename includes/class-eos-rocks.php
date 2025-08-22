<?php
/**
 * EOS Rocks Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Rocks {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_eos_save_rock', array($this, 'ajax_save_rock'));
        add_action('wp_ajax_eos_update_rock_progress', array($this, 'ajax_update_progress'));
        add_action('wp_ajax_eos_delete_rock', array($this, 'ajax_delete_rock'));
        add_action('wp_ajax_eos_complete_rock', array($this, 'ajax_complete_rock'));
    }
    
    /**
     * Get all rocks with optional filtering
     */
    public static function get_rocks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'quarter' => '',
            'owner' => '',
            'priority' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'due_date',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = EOS_Database::get_table_name('rocks');
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['quarter'])) {
            $where_clauses[] = "quarter = %s";
            $where_values[] = $args['quarter'];
        }
        
        if (!empty($args['owner'])) {
            $where_clauses[] = "owner = %s";
            $where_values[] = $args['owner'];
        }
        
        if (!empty($args['priority'])) {
            $where_clauses[] = "priority = %s";
            $where_values[] = $args['priority'];
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
        
        $rocks = $wpdb->get_results($sql, ARRAY_A);
        
        // Add calculated fields
        foreach ($rocks as &$rock) {
            $rock = self::enhance_rock_data($rock);
        }
        
        return $rocks;
    }
    
    /**
     * Get a single rock by ID
     */
    public static function get_rock($id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        $rock = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($rock) {
            $rock = self::enhance_rock_data($rock);
        }
        
        return $rock;
    }
    
    /**
     * Save a rock (create or update)
     */
    public static function save_rock($data) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        
        // Sanitize and validate data
        $rock_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'owner' => sanitize_text_field($data['owner']),
            'due_date' => sanitize_text_field($data['due_date']),
            'priority' => in_array($data['priority'], array('high', 'medium', 'low')) ? $data['priority'] : 'medium',
            'progress' => intval($data['progress']),
            'status' => in_array($data['status'], array('active', 'completed', 'on-hold', 'cancelled')) ? $data['status'] : 'active',
            'quarter' => self::determine_quarter($data['due_date'])
        );
        
        // Validate required fields
        if (empty($rock_data['title'])) {
            return array('success' => false, 'message' => __('Rock title is required.', 'eos-manager'));
        }
        
        if (empty($rock_data['due_date'])) {
            return array('success' => false, 'message' => __('Due date is required.', 'eos-manager'));
        }
        
        // Validate date
        if (!self::is_valid_date($rock_data['due_date'])) {
            return array('success' => false, 'message' => __('Invalid due date format.', 'eos-manager'));
        }
        
        // Ensure progress is within valid range
        $rock_data['progress'] = max(0, min(100, $rock_data['progress']));
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing rock
            $rock_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table,
                $rock_data,
                array('id' => intval($data['id'])),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $rock_id = intval($data['id']);
                self::log_activity('updated', $rock_id, $rock_data['title']);
                return array('success' => true, 'message' => __('Rock updated successfully.', 'eos-manager'), 'rock_id' => $rock_id);
            }
        } else {
            // Create new rock
            $rock_data['created_at'] = current_time('mysql');
            $rock_data['created_by'] = get_current_user_id();
            
            $result = $wpdb->insert(
                $table,
                $rock_data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d')
            );
            
            if ($result !== false) {
                $rock_id = $wpdb->insert_id;
                self::log_activity('created', $rock_id, $rock_data['title']);
                return array('success' => true, 'message' => __('Rock created successfully.', 'eos-manager'), 'rock_id' => $rock_id);
            }
        }
        
        return array('success' => false, 'message' => __('Failed to save rock. Please try again.', 'eos-manager'));
    }
    
    /**
     * Update rock progress
     */
    public static function update_progress($rock_id, $progress) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        $progress = max(0, min(100, intval($progress)));
        
        $result = $wpdb->update(
            $table,
            array(
                'progress' => $progress,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $rock_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $rock = self::get_rock($rock_id);
            self::log_activity('progress_updated', $rock_id, $rock['title'], array('progress' => $progress));
            
            // Auto-complete if 100%
            if ($progress >= 100) {
                self::complete_rock($rock_id);
            }
            
            return array('success' => true, 'progress' => $progress);
        }
        
        return array('success' => false, 'message' => __('Failed to update progress.', 'eos-manager'));
    }
    
    /**
     * Complete a rock
     */
    public static function complete_rock($rock_id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'completed',
                'progress' => 100,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $rock_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $rock = self::get_rock($rock_id);
            self::log_activity('completed', $rock_id, $rock['title']);
            
            return array('success' => true, 'message' => __('Rock completed successfully!', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to complete rock.', 'eos-manager'));
    }
    
    /**
     * Delete a rock
     */
    public static function delete_rock($rock_id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        $rock = self::get_rock($rock_id);
        
        if (!$rock) {
            return array('success' => false, 'message' => __('Rock not found.', 'eos-manager'));
        }
        
        $result = $wpdb->delete(
            $table,
            array('id' => $rock_id),
            array('%d')
        );
        
        if ($result !== false) {
            self::log_activity('deleted', $rock_id, $rock['title']);
            return array('success' => true, 'message' => __('Rock deleted successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to delete rock.', 'eos-manager'));
    }
    
    /**
     * Get rocks statistics for dashboard
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        $current_quarter = self::get_current_quarter();
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' AND progress >= 75 THEN 1 ELSE 0 END) as on_track,
                SUM(CASE WHEN status = 'active' AND progress BETWEEN 25 AND 74 THEN 1 ELSE 0 END) as at_risk,
                SUM(CASE WHEN status = 'active' AND progress < 25 THEN 1 ELSE 0 END) as behind,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                AVG(progress) as avg_progress
            FROM $table 
            WHERE quarter = %s OR quarter IS NULL
        ", $current_quarter), ARRAY_A);
        
        return array(
            'total' => intval($stats['total']),
            'on_track' => intval($stats['on_track']),
            'at_risk' => intval($stats['at_risk']),
            'behind' => intval($stats['behind']),
            'completed' => intval($stats['completed']),
            'avg_progress' => round(floatval($stats['avg_progress']), 1)
        );
    }
    
    /**
     * Get the most urgent rock
     */
    public static function get_most_urgent() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        
        $rock = $wpdb->get_row("
            SELECT *, DATEDIFF(due_date, CURDATE()) as days_remaining
            FROM $table 
            WHERE status = 'active' 
            AND due_date >= CURDATE()
            ORDER BY due_date ASC, progress ASC
            LIMIT 1
        ", ARRAY_A);
        
        if ($rock) {
            $rock = self::enhance_rock_data($rock);
        }
        
        return $rock;
    }
    
    /**
     * Get rocks by owner
     */
    public static function get_rocks_by_owner($owner) {
        return self::get_rocks(array('owner' => $owner, 'status' => 'active'));
    }
    
    /**
     * Get overdue rocks
     */
    public static function get_overdue_rocks() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('rocks');
        
        $rocks = $wpdb->get_results("
            SELECT * FROM $table 
            WHERE status = 'active' 
            AND due_date < CURDATE()
            ORDER BY due_date ASC
        ", ARRAY_A);
        
        foreach ($rocks as &$rock) {
            $rock = self::enhance_rock_data($rock);
        }
        
        return $rocks;
    }
    
    /**
     * Enhance rock data with calculated fields
     */
    private static function enhance_rock_data($rock) {
        if (!$rock) return $rock;
        
        // Calculate days remaining
        $due_date = new DateTime($rock['due_date']);
        $today = new DateTime();
        $rock['days_remaining'] = $due_date->diff($today)->days;
        
        if ($due_date < $today) {
            $rock['days_remaining'] = -$rock['days_remaining']; // Negative for overdue
        }
        
        // Determine status based on progress and due date
        $rock['calculated_status'] = self::calculate_rock_status($rock);
        
        // Format dates
        $rock['due_date_formatted'] = date_i18n(get_option('date_format'), strtotime($rock['due_date']));
        $rock['created_at_formatted'] = date_i18n(get_option('date_format'), strtotime($rock['created_at']));
        
        return $rock;
    }
    
    /**
     * Calculate rock status based on progress and timeline
     */
    private static function calculate_rock_status($rock) {
        if ($rock['status'] !== 'active') {
            return $rock['status'];
        }
        
        $progress = intval($rock['progress']);
        $days_remaining = intval($rock['days_remaining']);
        
        if ($days_remaining < 0) {
            return 'overdue';
        } elseif ($progress >= 75) {
            return 'on-track';
        } elseif ($progress >= 25 && $days_remaining > 7) {
            return 'at-risk';
        } else {
            return 'behind';
        }
    }
    
    /**
     * Determine quarter based on date
     */
    private static function determine_quarter($date) {
        $timestamp = strtotime($date);
        $year = date('Y', $timestamp);
        $month = intval(date('n', $timestamp));
        
        if ($month <= 3) {
            return $year . 'Q1';
        } elseif ($month <= 6) {
            return $year . 'Q2';
        } elseif ($month <= 9) {
            return $year . 'Q3';
        } else {
            return $year . 'Q4';
        }
    }
    
    /**
     * Get current quarter
     */
    private static function get_current_quarter() {
        return self::determine_quarter(current_time('Y-m-d'));
    }
    
    /**
     * Validate date format
     */
    private static function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Log rock activity
     */
    private static function log_activity($action, $rock_id, $rock_title, $meta = array()) {
        $activity_data = array(
            'type' => 'rocks',
            'action' => $action,
            'item_id' => $rock_id,
            'title' => sprintf(__('Rock %s: %s', 'eos-manager'), $action, $rock_title),
            'description' => '',
            'meta' => $meta,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        switch ($action) {
            case 'created':
                $activity_data['description'] = sprintf(__('New rock "%s" was created', 'eos-manager'), $rock_title);
                $activity_data['icon'] = '🎯';
                break;
            case 'updated':
                $activity_data['description'] = sprintf(__('Rock "%s" was updated', 'eos-manager'), $rock_title);
                $activity_data['icon'] = '✏️';
                break;
            case 'progress_updated':
                $activity_data['description'] = sprintf(__('Progress updated to %d%% for "%s"', 'eos-manager'), $meta['progress'], $rock_title);
                $activity_data['icon'] = '📈';
                break;
            case 'completed':
                $activity_data['description'] = sprintf(__('Rock "%s" was completed! 🎉', 'eos-manager'), $rock_title);
                $activity_data['icon'] = '✅';
                break;
            case 'deleted':
                $activity_data['description'] = sprintf(__('Rock "%s" was deleted', 'eos-manager'), $rock_title);
                $activity_data['icon'] = '🗑️';
                break;
        }
        
        // This would typically be handled by a separate activity logging system
        do_action('eos_log_activity', $activity_data);
    }
    
    /**
     * AJAX handler for saving rocks
     */
    public function ajax_save_rock() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $rock_data = $_POST['rock_data'];
        $result = self::save_rock($rock_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for updating progress
     */
    public function ajax_update_progress() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $rock_id = intval($_POST['rock_id']);
        $progress = intval($_POST['progress']);
        
        $result = self::update_progress($rock_id, $progress);
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for completing rocks
     */
    public function ajax_complete_rock() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $rock_id = intval($_POST['rock_id']);
        $result = self::complete_rock($rock_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for deleting rocks
     */
    public function ajax_delete_rock() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $rock_id = intval($_POST['rock_id']);
        $result = self::delete_rock($rock_id);
        
        wp_send_json($result);
    }
}
