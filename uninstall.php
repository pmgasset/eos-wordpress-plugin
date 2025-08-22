<?php
/**
 * Uninstall script for EOS Manager
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load the database class
require_once plugin_dir_path(__FILE__) . 'includes/class-eos-database.php';

// Drop all tables
EOS_Database::drop_tables();

// Remove all options
delete_option('eos_manager_version');
delete_option('eos_manager_db_version');
delete_option('eos_api_key');

// Remove any scheduled events
wp_clear_scheduled_hook('eos_daily_scorecard_reminder');
wp_clear_scheduled_hook('eos_weekly_l10_reminder');
wp_clear_scheduled_hook('eos_daily_meeting_reminder');
?>