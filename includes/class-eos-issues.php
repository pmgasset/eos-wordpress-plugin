<?php
/**
 * EOS Issues Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Issues {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_eos_save_issue', array($this, 'ajax_save_issue'));
        add_action('wp_ajax_eos_update_issue_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_eos_delete_issue', array($this, 'ajax_delete_issue'));
        add_action('wp_ajax_eos_solve_issue', array($this, 'ajax_solve_issue'));
        add_action('wp_ajax_eos_assign_issue', array($this, 'ajax_assign_issue'));
    }
    
    /**
     * Get all issues with optional filtering
     */
    public static function get_issues($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'priority' => '',
            'assignee' => '',
            'category' => '',
            'related_rock_id' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = EOS_Database::get_table_name('issues');
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['priority'])) {
            $where_clauses[] = "priority = %s";
            $where_values[] = $args['priority'];
        }
        
        if (!empty($args['assignee'])) {
            $where_clauses[] = "assignee = %s";
            $where_values[] = $args['assignee'];
        }
        
        if (!empty($args['category'])) {
            $where_clauses[] = "category = %s";
            $where_values[] = $args['category'];
        }
        
        if (!empty($args['related_rock_id'])) {
            $where_clauses[] = "related_rock_id = %d";
            $where_values[] = intval($args['related_rock_id']);
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
        
        $issues = $wpdb->get_results($sql, ARRAY_A);
        
        // Enhance issue data
        foreach ($issues as &$issue) {
            $issue = self::enhance_issue_data($issue);
        }
        
        return $issues;
    }
    
    /**
     * Get a single issue by ID
     */
    public static function get_issue($id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        $issue = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($issue) {
            $issue = self::enhance_issue_data($issue);
        }
        
        return $issue;
    }
    
    /**
     * Save an issue (create or update)
     */
    public static function save_issue($data) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        
        // Sanitize and validate data
        $issue_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'priority' => in_array($data['priority'], array('high', 'medium', 'low')) ? $data['priority'] : 'medium',
            'status' => in_array($data['status'], array('identified', 'discussing', 'solved', 'closed')) ? $data['status'] : 'identified',
            'assignee' => sanitize_text_field($data['assignee']),
            'category' => sanitize_text_field($data['category']) ?: 'general'
        );
        
        if (!empty($data['related_rock_id'])) {
            $issue_data['related_rock_id'] = intval($data['related_rock_id']);
        }
        
        // Validate required fields
        if (empty($issue_data['title'])) {
            return array('success' => false, 'message' => __('Issue title is required.', 'eos-manager'));
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing issue
            $issue_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table,
                $issue_data,
                array('id' => intval($data['id'])),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $issue_id = intval($data['id']);
                self::log_activity('updated', $issue_id, $issue_data['title']);
                return array('success' => true, 'message' => __('Issue updated successfully.', 'eos-manager'), 'issue_id' => $issue_id);
            }
        } else {
            // Create new issue
            $issue_data['created_at'] = current_time('mysql');
            $issue_data['created_by'] = get_current_user_id();
            
            $result = $wpdb->insert(
                $table,
                $issue_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
            );
            
            if ($result !== false) {
                $issue_id = $wpdb->insert_id;
                self::log_activity('created', $issue_id, $issue_data['title']);
                return array('success' => true, 'message' => __('Issue created successfully.', 'eos-manager'), 'issue_id' => $issue_id);
            }
        }
        
        return array('success' => false, 'message' => __('Failed to save issue. Please try again.', 'eos-manager'));
    }
    
    /**
     * Update issue status
     */
    public static function update_status($issue_id, $status) {
        global $wpdb;
        
        if (!in_array($status, array('identified', 'discussing', 'solved', 'closed'))) {
            return array('success' => false, 'message' => __('Invalid status.', 'eos-manager'));
        }
        
        $table = EOS_Database::get_table_name('issues');
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        // Set resolved date if solving
        if ($status === 'solved') {
            $update_data['resolved_at'] = current_time('mysql');
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $issue_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $issue = self::get_issue($issue_id);
            self::log_activity('status_changed', $issue_id, $issue['title'], array('new_status' => $status));
            
            return array('success' => true, 'status' => $status, 'message' => __('Issue status updated.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to update status.', 'eos-manager'));
    }
    
    /**
     * Solve an issue
     */
    public static function solve_issue($issue_id, $solution = '') {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        
        $update_data = array(
            'status' => 'solved',
            'resolved_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($solution)) {
            $current_issue = self::get_issue($issue_id);
            $updated_description = $current_issue['description'] . "\n\n**Solution:** " . wp_kses_post($solution);
            $update_data['description'] = $updated_description;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $issue_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $issue = self::get_issue($issue_id);
            self::log_activity('solved', $issue_id, $issue['title'], array('solution' => $solution));
            
            return array('success' => true, 'message' => __('Issue solved successfully!', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to solve issue.', 'eos-manager'));
    }
    
    /**
     * Assign issue to someone
     */
    public static function assign_issue($issue_id, $assignee) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        
        $result = $wpdb->update(
            $table,
            array(
                'assignee' => sanitize_text_field($assignee),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $issue_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $issue = self::get_issue($issue_id);
            self::log_activity('assigned', $issue_id, $issue['title'], array('assignee' => $assignee));
            
            return array('success' => true, 'assignee' => $assignee, 'message' => __('Issue assigned successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to assign issue.', 'eos-manager'));
    }
    
    /**
     * Delete an issue
     */
    public static function delete_issue($issue_id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        $issue = self::get_issue($issue_id);
        
        if (!$issue) {
            return array('success' => false, 'message' => __('Issue not found.', 'eos-manager'));
        }
        
        $result = $wpdb->delete(
            $table,
            array('id' => $issue_id),
            array('%d')
        );
        
        if ($result !== false) {
            self::log_activity('deleted', $issue_id, $issue['title']);
            return array('success' => true, 'message' => __('Issue deleted successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to delete issue.', 'eos-manager'));
    }
    
    /**
     * Get issues statistics for dashboard
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('identified', 'discussing') THEN 1 ELSE 0 END) as total_open,
                SUM(CASE WHEN priority = 'high' AND status IN ('identified', 'discussing') THEN 1 ELSE 0 END) as high_priority,
                SUM(CASE WHEN priority = 'medium' AND status IN ('identified', 'discussing') THEN 1 ELSE 0 END) as medium_priority,
                SUM(CASE WHEN priority = 'low' AND status IN ('identified', 'discussing') THEN 1 ELSE 0 END) as low_priority,
                SUM(CASE WHEN status = 'solved' THEN 1 ELSE 0 END) as solved,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
            FROM $table
        ", ARRAY_A);
        
        return array(
            'total' => intval($stats['total']),
            'total_open' => intval($stats['total_open']),
            'high_priority' => intval($stats['high_priority']),
            'medium_priority' => intval($stats['medium_priority']),
            'low_priority' => intval($stats['low_priority']),
            'solved' => intval($stats['solved']),
            'closed' => intval($stats['closed'])
        );
    }
    
    /**
     * Get top priority issue
     */
    public static function get_top_priority_issue() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        
        $issue = $wpdb->get_row("
            SELECT * FROM $table 
            WHERE status IN ('identified', 'discussing')
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END ASC,
                created_at ASC
            LIMIT 1
        ", ARRAY_A);
        
        if ($issue) {
            $issue = self::enhance_issue_data($issue);
        }
        
        return $issue;
    }
    
    /**
     * Get issues by assignee
     */
    public static function get_issues_by_assignee($assignee) {
        return self::get_issues(array(
            'assignee' => $assignee,
            'status' => array('identified', 'discussing')
        ));
    }
    
    /**
     * Get issues by priority
     */
    public static function get_issues_by_priority($priority) {
        return self::get_issues(array(
            'priority' => $priority,
            'status' => array('identified', 'discussing')
        ));
    }
    
    /**
     * Get issues related to a rock
     */
    public static function get_issues_by_rock($rock_id) {
        return self::get_issues(array('related_rock_id' => $rock_id));
    }
    
    /**
     * Get issue categories
     */
    public static function get_categories() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('issues');
        
        $categories = $wpdb->get_col("
            SELECT DISTINCT category 
            FROM $table 
            WHERE category IS NOT NULL AND category != ''
            ORDER BY category ASC
        ");
        
        // Add default categories if none exist
        if (empty($categories)) {
            $categories = array(
                'general',
                'operations',
                'sales',
                'marketing',
                'finance',
                'hr',
                'technology',
                'customer-service'
            );
        }
        
        return $categories;
    }
    
    /**
     * Enhance issue data with calculated fields
     */
    private static function enhance_issue_data($issue) {
        if (!$issue) return $issue;
        
        // Format dates
        $issue['created_at_formatted'] = date_i18n(get_option('date_format'), strtotime($issue['created_at']));
        $issue['updated_at_formatted'] = date_i18n(get_option('date_format'), strtotime($issue['updated_at']));
        
        if ($issue['resolved_at']) {
            $issue['resolved_at_formatted'] = date_i18n(get_option('date_format'), strtotime($issue['resolved_at']));
        }
        
        // Calculate age
        $created_date = new DateTime($issue['created_at']);
        $now = new DateTime();
        $age = $created_date->diff($now);
        $issue['age_days'] = $age->days;
        
        // Get related rock info if exists
        if ($issue['related_rock_id']) {
            $rock = EOS_Rocks::get_rock($issue['related_rock_id']);
            $issue['related_rock'] = $rock ? $rock['title'] : '';
        }
        
        // Priority display
        $priority_labels = array(
            'high' => __('High Priority', 'eos-manager'),
            'medium' => __('Medium Priority', 'eos-manager'),
            'low' => __('Low Priority', 'eos-manager')
        );
        $issue['priority_label'] = $priority_labels[$issue['priority']] ?? $issue['priority'];
        
        // Status display
        $status_labels = array(
            'identified' => __('Identified', 'eos-manager'),
            'discussing' => __('Discussing', 'eos-manager'),
            'solved' => __('Solved', 'eos-manager'),
            'closed' => __('Closed', 'eos-manager')
        );
        $issue['status_label'] = $status_labels[$issue['status']] ?? $issue['status'];
        
        return $issue;
    }
    
    /**
     * Log issue activity
     */
    private static function log_activity($action, $issue_id, $issue_title, $meta = array()) {
        $activity_data = array(
            'type' => 'issues',
            'action' => $action,
            'item_id' => $issue_id,
            'title' => sprintf(__('Issue %s: %s', 'eos-manager'), $action, $issue_title),
            'meta' => $meta,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        switch ($action) {
            case 'created':
                $activity_data['description'] = sprintf(__('New issue "%s" was identified', 'eos-manager'), $issue_title);
                $activity_data['icon'] = '⚠️';
                break;
            case 'updated':
                $activity_data['description'] = sprintf(__('Issue "%s" was updated', 'eos-manager'), $issue_title);
                $activity_data['icon'] = '✏️';
                break;
            case 'status_changed':
                $activity_data['description'] = sprintf(__('Issue "%s" status changed to %s', 'eos-manager'), $issue_title, $meta['new_status']);
                $activity_data['icon'] = '🔄';
                break;
            case 'solved':
                $activity_data['description'] = sprintf(__('Issue "%s" was solved! 🎉', 'eos-manager'), $issue_title);
                $activity_data['icon'] = '✅';
                break;
            case 'assigned':
                $activity_data['description'] = sprintf(__('Issue "%s" was assigned to %s', 'eos-manager'), $issue_title, $meta['assignee']);
                $activity_data['icon'] = '👤';
                break;
            case 'deleted':
                $activity_data['description'] = sprintf(__('Issue "%s" was deleted', 'eos-manager'), $issue_title);
                $activity_data['icon'] = '🗑️';
                break;
        }
        
        do_action('eos_log_activity', $activity_data);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_save_issue() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $issue_data = $_POST['issue_data'];
        $result = self::save_issue($issue_data);
        
        wp_send_json($result);
    }
    
    public function ajax_update_status() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $issue_id = intval($_POST['issue_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $result = self::update_status($issue_id, $status);
        wp_send_json($result);
    }
    
    public function ajax_solve_issue() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $issue_id = intval($_POST['issue_id']);
        $solution = sanitize_textarea_field($_POST['solution']);
        
        $result = self::solve_issue($issue_id, $solution);
        wp_send_json($result);
    }
    
    public function ajax_assign_issue() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $issue_id = intval($_POST['issue_id']);
        $assignee = sanitize_text_field($_POST['assignee']);
        
        $result = self::assign_issue($issue_id, $assignee);
        wp_send_json($result);
    }
    
    public function ajax_delete_issue() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $issue_id = intval($_POST['issue_id']);
        $result = self::delete_issue($issue_id);
        
        wp_send_json($result);
    }
}
