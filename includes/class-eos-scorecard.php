<?php
/**
 * EOS Scorecard Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Scorecard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_eos_save_metric', array($this, 'ajax_save_metric'));
        add_action('wp_ajax_eos_update_metric_value', array($this, 'ajax_update_value'));
        add_action('wp_ajax_eos_delete_metric', array($this, 'ajax_delete_metric'));
        add_action('wp_ajax_eos_save_scorecard_data', array($this, 'ajax_save_scorecard_data'));
        
        // Schedule weekly scorecard reminders
        add_action('eos_weekly_scorecard_reminder', array($this, 'send_scorecard_reminders'));
    }
    
    /**
     * Get all metrics with optional filtering
     */
    public static function get_metrics($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'is_active' => true,
            'owner' => '',
            'frequency' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'sort_order',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = EOS_Database::get_table_name('scorecard_metrics');
        
        $where_clauses = array();
        $where_values = array();
        
        if ($args['is_active'] !== null) {
            $where_clauses[] = "is_active = %d";
            $where_values[] = $args['is_active'] ? 1 : 0;
        }
        
        if (!empty($args['owner'])) {
            $where_clauses[] = "owner = %s";
            $where_values[] = $args['owner'];
        }
        
        if (!empty($args['frequency'])) {
            $where_clauses[] = "frequency = %s";
            $where_values[] = $args['frequency'];
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
        
        $metrics = $wpdb->get_results($sql, ARRAY_A);
        
        // Enhance metrics with current week data
        foreach ($metrics as &$metric) {
            $metric = self::enhance_metric_data($metric);
        }
        
        return $metrics;
    }
    
    /**
     * Get a single metric by ID
     */
    public static function get_metric($id) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('scorecard_metrics');
        $metric = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($metric) {
            $metric = self::enhance_metric_data($metric);
        }
        
        return $metric;
    }
    
    /**
     * Save a metric (create or update)
     */
    public static function save_metric($data) {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('scorecard_metrics');
        
        // Sanitize and validate data
        $metric_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'goal' => floatval($data['goal']),
            'owner' => sanitize_text_field($data['owner']),
            'frequency' => in_array($data['frequency'], array('daily', 'weekly', 'monthly', 'quarterly')) ? $data['frequency'] : 'weekly',
            'unit' => sanitize_text_field($data['unit']),
            'is_active' => !empty($data['is_active']),
            'sort_order' => intval($data['sort_order']) ?: 0
        );
        
        // Validate required fields
        if (empty($metric_data['name'])) {
            return array('success' => false, 'message' => __('Metric name is required.', 'eos-manager'));
        }
        
        if ($metric_data['goal'] <= 0) {
            return array('success' => false, 'message' => __('Goal must be greater than 0.', 'eos-manager'));
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing metric
            $metric_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table,
                $metric_data,
                array('id' => intval($data['id'])),
                array('%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $metric_id = intval($data['id']);
                self::log_activity('updated', $metric_id, $metric_data['name']);
                return array('success' => true, 'message' => __('Metric updated successfully.', 'eos-manager'), 'metric_id' => $metric_id);
            }
        } else {
            // Create new metric
            $metric_data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $table,
                $metric_data,
                array('%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s')
            );
            
            if ($result !== false) {
                $metric_id = $wpdb->insert_id;
                self::log_activity('created', $metric_id, $metric_data['name']);
                return array('success' => true, 'message' => __('Metric created successfully.', 'eos-manager'), 'metric_id' => $metric_id);
            }
        }
        
        return array('success' => false, 'message' => __('Failed to save metric. Please try again.', 'eos-manager'));
    }
    
    /**
     * Update metric current value and status
     */
    public static function update_metric_value($metric_id, $value, $week_ending = null) {
        global $wpdb;
        
        $metrics_table = EOS_Database::get_table_name('scorecard_metrics');
        $data_table = EOS_Database::get_table_name('scorecard_data');
        
        if (!$week_ending) {
            $week_ending = self::get_week_ending_date();
        }
        
        // Update current value in metrics table
        $metric = self::get_metric($metric_id);
        if (!$metric) {
            return array('success' => false, 'message' => __('Metric not found.', 'eos-manager'));
        }
        
        $goal = floatval($metric['goal']);
        $current_value = floatval($value);
        
        // Determine status based on goal comparison
        $status = self::calculate_status($current_value, $goal);
        
        // Update metrics table
        $result1 = $wpdb->update(
            $metrics_table,
            array(
                'current_value' => $current_value,
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $metric_id),
            array('%f', '%s', '%s'),
            array('%d')
        );
        
        // Insert/update weekly data
        $result2 = $wpdb->replace(
            $data_table,
            array(
                'metric_id' => $metric_id,
                'value' => $current_value,
                'week_ending' => $week_ending,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id()
            ),
            array('%d', '%f', '%s', '%s', '%d')
        );
        
        if ($result1 !== false && $result2 !== false) {
            self::log_activity('value_updated', $metric_id, $metric['name'], array(
                'value' => $current_value,
                'goal' => $goal,
                'status' => $status
            ));
            
            return array(
                'success' => true, 
                'value' => $current_value,
                'status' => $status,
                'message' => __('Metric value updated successfully.', 'eos-manager')
            );
        }
        
        return array('success' => false, 'message' => __('Failed to update metric value.', 'eos-manager'));
    }
    
    /**
     * Save multiple scorecard values at once
     */
    public static function save_scorecard_data($data) {
        global $wpdb;
        
        $week_ending = $data['week_ending'] ?? self::get_week_ending_date();
        $notes = wp_kses_post($data['notes'] ?? '');
        $success_count = 0;
        $errors = array();
        
        if (empty($data['metrics']) || !is_array($data['metrics'])) {
            return array('success' => false, 'message' => __('No metric data provided.', 'eos-manager'));
        }
        
        foreach ($data['metrics'] as $metric_id => $value) {
            if (empty($value) && $value !== '0') continue;
            
            $result = self::update_metric_value($metric_id, $value, $week_ending);
            
            if ($result['success']) {
                $success_count++;
            } else {
                $errors[] = $result['message'];
            }
        }
        
        // Save notes if provided
        if (!empty($notes)) {
            update_option('eos_scorecard_notes_' . $week_ending, $notes);
        }
        
        if ($success_count > 0) {
            self::log_activity('scorecard_updated', 0, sprintf(__('%d metrics updated', 'eos-manager'), $success_count));
            
            $message = sprintf(
                _n('%d metric updated successfully.', '%d metrics updated successfully.', $success_count, 'eos-manager'),
                $success_count
            );
            
            if (!empty($errors)) {
                $message .= ' ' . sprintf(__('However, there were %d errors.', 'eos-manager'), count($errors));
            }
            
            return array('success' => true, 'message' => $message, 'updated_count' => $success_count);
        }
        
        return array('success' => false, 'message' => __('Failed to update scorecard data.', 'eos-manager'), 'errors' => $errors);
    }
    
    /**
     * Delete a metric
     */
    public static function delete_metric($metric_id) {
        global $wpdb;
        
        $metrics_table = EOS_Database::get_table_name('scorecard_metrics');
        $data_table = EOS_Database::get_table_name('scorecard_data');
        
        $metric = self::get_metric($metric_id);
        
        if (!$metric) {
            return array('success' => false, 'message' => __('Metric not found.', 'eos-manager'));
        }
        
        // Delete historical data first
        $wpdb->delete($data_table, array('metric_id' => $metric_id), array('%d'));
        
        // Delete metric
        $result = $wpdb->delete(
            $metrics_table,
            array('id' => $metric_id),
            array('%d')
        );
        
        if ($result !== false) {
            self::log_activity('deleted', $metric_id, $metric['name']);
            return array('success' => true, 'message' => __('Metric deleted successfully.', 'eos-manager'));
        }
        
        return array('success' => false, 'message' => __('Failed to delete metric.', 'eos-manager'));
    }
    
    /**
     * Get scorecard statistics for dashboard
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = EOS_Database::get_table_name('scorecard_metrics');
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'on-track' AND is_active = 1 THEN 1 ELSE 0 END) as on_target,
                SUM(CASE WHEN status = 'above-target' AND is_active = 1 THEN 1 ELSE 0 END) as above_target,
                SUM(CASE WHEN status = 'at-risk' AND is_active = 1 THEN 1 ELSE 0 END) as at_risk,
                SUM(CASE WHEN status = 'behind' AND is_active = 1 THEN 1 ELSE 0 END) as behind
            FROM $table
        ", ARRAY_A);
        
        $active = intval($stats['active']);
        $on_target = intval($stats['on_target']) + intval($stats['above_target']);
        
        return array(
            'total' => intval($stats['total']),
            'active' => $active,
            'on_target' => $on_target,
            'above_target' => intval($stats['above_target']),
            'at_risk' => intval($stats['at_risk']),
            'behind' => intval($stats['behind']),
            'health_percentage' => $active > 0 ? round(($on_target / $active) * 100, 1) : 0
        );
    }
    
    /**
     * Get historical data for a metric
     */
    public static function get_metric_history($metric_id, $weeks = 13) {
        global $wpdb;
        
        $data_table = EOS_Database::get_table_name('scorecard_data');
        
        $history = $wpdb->get_results($wpdb->prepare("
            SELECT value, week_ending, notes
            FROM $data_table
            WHERE metric_id = %d
            ORDER BY week_ending DESC
            LIMIT %d
        ", $metric_id, $weeks), ARRAY_A);
        
        return array_reverse($history); // Show oldest first for charts
    }
    
    /**
     * Get week ending date (Sunday)
     */
    public static function get_week_ending_date($date = null) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $timestamp = strtotime($date);
        $day_of_week = date('w', $timestamp); // 0 = Sunday, 6 = Saturday
        
        // Calculate days until next Sunday
        $days_until_sunday = (7 - $day_of_week) % 7;
        if ($days_until_sunday === 0 && date('w', $timestamp) !== 0) {
            $days_until_sunday = 7; // If today is not Sunday, go to next Sunday
        }
        
        return date('Y-m-d', strtotime("+$days_until_sunday days", $timestamp));
    }
    
    /**
     * Calculate status based on value vs goal
     */
    private static function calculate_status($value, $goal) {
        if ($value >= $goal * 1.1) { // 10% above goal
            return 'above-target';
        } elseif ($value >= $goal) {
            return 'on-track';
        } elseif ($value >= $goal * 0.8) { // Within 20% of goal
            return 'at-risk';
        } else {
            return 'behind';
        }
    }
    
    /**
     * Enhance metric data with current status and trends
     */
    private static function enhance_metric_data($metric) {
        if (!$metric) return $metric;
        
        // Format current value
        $metric['current_value_formatted'] = self::format_metric_value($metric['current_value'], $metric['unit']);
        $metric['goal_formatted'] = self::format_metric_value($metric['goal'], $metric['unit']);
        
        // Calculate percentage of goal achieved
        if ($metric['goal'] > 0) {
            $metric['goal_percentage'] = round(($metric['current_value'] / $metric['goal']) * 100, 1);
        } else {
            $metric['goal_percentage'] = 0;
        }
        
        // Get trend data (last 4 weeks)
        $history = self::get_metric_history($metric['id'], 4);
        $metric['trend'] = self::calculate_trend($history);
        
        // Status display
        $status_labels = array(
            'on-track' => __('On Track', 'eos-manager'),
            'above-target' => __('Above Target', 'eos-manager'),
            'at-risk' => __('At Risk', 'eos-manager'),
            'behind' => __('Behind', 'eos-manager')
        );
        $metric['status_label'] = $status_labels[$metric['status']] ?? $metric['status'];
        
        // Format dates
        $metric['created_at_formatted'] = date_i18n(get_option('date_format'), strtotime($metric['created_at']));
        $metric['updated_at_formatted'] = date_i18n(get_option('date_format'), strtotime($metric['updated_at']));
        
        return $metric;
    }
    
    /**
     * Format metric value with unit
     */
    private static function format_metric_value($value, $unit) {
        $formatted_value = number_format($value, 2);
        
        // Remove unnecessary decimals
        if ($value == intval($value)) {
            $formatted_value = number_format($value, 0);
        }
        
        if (!empty($unit)) {
            return $formatted_value . ' ' . $unit;
        }
        
        return $formatted_value;
    }
    
    /**
     * Calculate trend from historical data
     */
    private static function calculate_trend($history) {
        if (count($history) < 2) {
            return 'stable';
        }
        
        $values = array_column($history, 'value');
        $first_half = array_slice($values, 0, floor(count($values) / 2));
        $second_half = array_slice($values, floor(count($values) / 2));
        
        $first_avg = array_sum($first_half) / count($first_half);
        $second_avg = array_sum($second_half) / count($second_half);
        
        $change_percent = ($second_avg - $first_avg) / $first_avg * 100;
        
        if ($change_percent > 5) {
            return 'up';
        } elseif ($change_percent < -5) {
            return 'down';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Send scorecard reminders
     */
    public function send_scorecard_reminders() {
        $metrics = self::get_metrics(array('is_active' => true));
        $owners = array();
        
        foreach ($metrics as $metric) {
            if (!empty($metric['owner']) && is_email($metric['owner'])) {
                $owners[$metric['owner']][] = $metric;
            }
        }
        
        foreach ($owners as $email => $owner_metrics) {
            self::send_owner_reminder($email, $owner_metrics);
        }
    }
    
    /**
     * Send reminder to metric owner
     */
    private static function send_owner_reminder($email, $metrics) {
        $subject = __('Weekly Scorecard Update Reminder', 'eos-manager');
        $week_ending = self::get_week_ending_date();
        
        $message = sprintf(
            __("Hello!\n\nThis is a reminder to update your scorecard metrics for the week ending %s.\n\nYour metrics:\n\n", 'eos-manager'),
            date_i18n(get_option('date_format'), strtotime($week_ending))
        );
        
        foreach ($metrics as $metric) {
            $message .= sprintf("• %s (Goal: %s)\n", $metric['name'], $metric['goal_formatted']);
        }
        
        $message .= sprintf(
            __("\nPlease update your metrics in the EOS Manager: %s\n\nThank you!", 'eos-manager'),
            admin_url('admin.php?page=eos-scorecard')
        );
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Log scorecard activity
     */
    private static function log_activity($action, $metric_id, $metric_name, $meta = array()) {
        $activity_data = array(
            'type' => 'scorecard',
            'action' => $action,
            'item_id' => $metric_id,
            'title' => sprintf(__('Scorecard %s: %s', 'eos-manager'), $action, $metric_name),
            'meta' => $meta,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        switch ($action) {
            case 'created':
                $activity_data['description'] = sprintf(__('New metric "%s" was added to scorecard', 'eos-manager'), $metric_name);
                $activity_data['icon'] = '📊';
                break;
            case 'updated':
                $activity_data['description'] = sprintf(__('Metric "%s" was updated', 'eos-manager'), $metric_name);
                $activity_data['icon'] = '✏️';
                break;
            case 'value_updated':
                $activity_data['description'] = sprintf(
                    __('"%s" updated to %s (Goal: %s) - %s', 'eos-manager'), 
                    $metric_name,
                    $meta['value'],
                    $meta['goal'],
                    $meta['status']
                );
                $activity_data['icon'] = '📈';
                break;
            case 'deleted':
                $activity_data['description'] = sprintf(__('Metric "%s" was removed from scorecard', 'eos-manager'), $metric_name);
                $activity_data['icon'] = '🗑️';
                break;
            case 'scorecard_updated':
                $activity_data['description'] = $metric_name; // Already formatted
                $activity_data['icon'] = '📋';
                break;
        }
        
        do_action('eos_log_activity', $activity_data);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_save_metric() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $metric_data = $_POST['metric_data'];
        $result = self::save_metric($metric_data);
        
        wp_send_json($result);
    }
    
    public function ajax_update_value() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $metric_id = intval($_POST['metric_id']);
        $value = floatval($_POST['value']);
        $week_ending = sanitize_text_field($_POST['week_ending']) ?: null;
        
        $result = self::update_metric_value($metric_id, $value, $week_ending);
        wp_send_json($result);
    }
    
    public function ajax_save_scorecard_data() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $scorecard_data = $_POST['scorecard_data'];
        $result = self::save_scorecard_data($scorecard_data);
        
        wp_send_json($result);
    }
    
    public function ajax_delete_metric() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $metric_id = intval($_POST['metric_id']);
        $result = self::delete_metric($metric_id);
        
        wp_send_json($result);
    }
}

?>