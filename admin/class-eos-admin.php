<?php
/**
 * EOS Admin Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Admin {
    
    public function __construct() {
        add_action('admin_init', array($this, 'init'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    public function init() {
        // Check if database tables exist
        if (!EOS_Database::tables_exist()) {
            EOS_Database::maybe_update_db();
        }
    }
    
    public function admin_notices() {
        // Check for any notices
        if (isset($_GET['eos_activated'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('EOS Manager activated successfully! You can now start managing your EOS data.', 'eos-manager') . '</p>';
            echo '</div>';
        }
    }
}
