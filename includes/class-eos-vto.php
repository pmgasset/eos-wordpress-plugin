<?php
/**
 * EOS Vision/Traction Organizer Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_VTO {
    
    public function __construct() {
        add_action('wp_ajax_eos_save_vto_section', array($this, 'ajax_save_section'));
    }
    
    public static function get_section($section) {
        global $wpdb;
        $table = EOS_Database::get_table_name('vto');
        
        $content = $wpdb->get_var($wpdb->prepare(
            "SELECT content FROM $table WHERE section = %s",
            $section
        ));
        
        return $content;
    }
    
    public static function save_section($section, $content) {
        global $wpdb;
        $table = EOS_Database::get_table_name('vto');
        
        $result = $wpdb->replace(
            $table,
            array(
                'section' => $section,
                'content' => wp_kses_post($content),
                'updated_by' => get_current_user_id()
            ),
            array('%s', '%s', '%d')
        );
        
        return $result !== false;
    }
    
    public static function get_last_updated() {
        global $wpdb;
        $table = EOS_Database::get_table_name('vto');
        
        $last_updated = $wpdb->get_var(
            "SELECT MAX(last_updated) FROM $table"
        );
        
        return $last_updated ? human_time_diff(strtotime($last_updated)) . ' ago' : 'Never';
    }
    
    public static function get_all_sections() {
        return array(
            'core-values' => __('Core Values', 'eos-manager'),
            'core-focus' => __('Core Focus', 'eos-manager'),
            '10-year-target' => __('10-Year Target', 'eos-manager'),
            'marketing-strategy' => __('Marketing Strategy', 'eos-manager'),
            '3-year-picture' => __('3-Year Picture', 'eos-manager'),
            '1-year-plan' => __('1-Year Plan', 'eos-manager'),
            'quarterly-rocks' => __('Quarterly Rocks', 'eos-manager'),
            'issues-list' => __('Issues List', 'eos-manager')
        );
    }
    
    public function ajax_save_section() {
        check_ajax_referer('eos_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $section = sanitize_key($_POST['section']);
        $content = wp_kses_post($_POST['content']);
        
        $result = self::save_section($section, $content);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Section saved successfully.', 'eos-manager')));
        } else {
            wp_send_json_error(array('message' => __('Failed to save section.', 'eos-manager')));
        }
    }
}
