<?php
/**
 * Plugin Name: EOS Manager
 * Plugin URI: https://your-domain.com/eos-manager
 * Description: Complete Entrepreneurial Operating System management for WordPress. Track your company's vital signs, manage rocks, run L10 meetings, and keep your team aligned.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: eos-manager
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EOS_MANAGER_VERSION', '1.0.0');
define('EOS_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EOS_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EOS_MANAGER_PLUGIN_FILE', __FILE__);

/**
 * Main EOS Manager Plugin Class
 */
class EOS_Manager {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add EOS quick links to admin bar
        add_action('admin_bar_menu', array($this, 'add_admin_bar_links'), 100);
        
        // AJAX handlers handled by module classes except scorecard
        add_action('wp_ajax_eos_save_scorecard', array($this, 'ajax_save_scorecard'));
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-database.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-rocks.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-scorecard.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-issues.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-meetings.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-todos.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-people.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-vto.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-integrations.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-rest-api.php';
        require_once EOS_MANAGER_PLUGIN_DIR . 'admin/class-eos-admin.php';
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Ensure database class is available
        require_once EOS_MANAGER_PLUGIN_DIR . 'includes/class-eos-database.php';

        // Create database tables
        EOS_Database::create_tables();
        
        // Set default options
        add_option('eos_manager_version', EOS_MANAGER_VERSION);
        add_option('eos_manager_db_version', '1.0');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('eos_daily_scorecard_reminder');
        wp_clear_scheduled_hook('eos_weekly_l10_reminder');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('eos-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize modules
        new EOS_Rocks();
        new EOS_Scorecard();
        new EOS_Issues();
        new EOS_Meetings();
        new EOS_People();
        new EOS_VTO();
        new EOS_Integrations();
        new EOS_REST_API();
        
        if (is_admin()) {
            new EOS_Admin();
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('EOS Manager', 'eos-manager'),
            __('⚡ EOS Manager', 'eos-manager'),
            'manage_options',
            'eos-manager',
            array($this, 'render_main_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2L2 7L12 12L22 7L12 2ZM2 17L12 22L22 17L12 12L2 17Z"/></svg>'),
            25
        );
        
        // Add submenu pages (hidden, accessed via main dashboard)
        add_submenu_page(
            null, // Hidden from menu
            __('Scorecard', 'eos-manager'),
            __('Scorecard', 'eos-manager'),
            'manage_options',
            'eos-scorecard',
            array($this, 'render_scorecard_page')
        );
        
        add_submenu_page(
            null,
            __('Rocks', 'eos-manager'),
            __('Rocks', 'eos-manager'),
            'manage_options',
            'eos-rocks',
            array($this, 'render_rocks_page')
        );
        
        add_submenu_page(
            null,
            __('Issues', 'eos-manager'),
            __('Issues', 'eos-manager'),
            'manage_options',
            'eos-issues',
            array($this, 'render_issues_page')
        );
        
        add_submenu_page(
            null,
            __('L10 Meetings', 'eos-manager'),
            __('L10 Meetings', 'eos-manager'),
            'manage_options',
            'eos-meetings',
            array($this, 'render_meetings_page')
        );
    }
    
    /**
     * Add quick links to admin bar
     */
    public function add_admin_bar_links($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node(array(
            'id'    => 'eos-l10',
            'title' => '🟣 L10 Meeting',
            'href'  => admin_url('admin.php?page=eos-meetings&action=start'),
            'meta'  => array('class' => 'eos-quick-link')
        ));
        
        $wp_admin_bar->add_node(array(
            'id'    => 'eos-scorecard',
            'title' => '📊 Scorecard',
            'href'  => admin_url('admin.php?page=eos-scorecard'),
            'meta'  => array('class' => 'eos-quick-link')
        ));
        
        $wp_admin_bar->add_node(array(
            'id'    => 'eos-rocks',
            'title' => '🎯 Rocks',
            'href'  => admin_url('admin.php?page=eos-rocks'),
            'meta'  => array('class' => 'eos-quick-link')
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on EOS pages
        if (strpos($hook, 'eos-') === false && $hook !== 'toplevel_page_eos-manager') {
            return;
        }
        
        wp_enqueue_style(
            'eos-admin-style',
            EOS_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EOS_MANAGER_VERSION
        );
        
        wp_enqueue_script(
            'eos-admin-script',
            EOS_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-api'),
            EOS_MANAGER_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('eos-admin-script', 'eosAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eos_nonce'),
            'restUrl' => rest_url('eos/v1/'),
            'restNonce' => wp_create_nonce('wp_rest')
        ));
    }
    
    /**
     * Enqueue frontend scripts (if needed for shortcodes)
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'eos-frontend-style',
            EOS_MANAGER_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            EOS_MANAGER_VERSION
        );
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        EOS_REST_API::register_routes();
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        include EOS_MANAGER_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Render scorecard page
     */
    public function render_scorecard_page() {
        include EOS_MANAGER_PLUGIN_DIR . 'admin/views/scorecard.php';
    }
    
    /**
     * Render rocks page
     */
    public function render_rocks_page() {
        include EOS_MANAGER_PLUGIN_DIR . 'admin/views/rocks.php';
    }
    
    /**
     * Render issues page
     */
    public function render_issues_page() {
        include EOS_MANAGER_PLUGIN_DIR . 'admin/views/issues.php';
    }
    
    /**
     * Render meetings page
     */
    public function render_meetings_page() {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'run') {
            include EOS_MANAGER_PLUGIN_DIR . 'admin/views/meeting-run.php';
        } else {
            include EOS_MANAGER_PLUGIN_DIR . 'admin/views/meetings.php';
        }
    }
    
    /**
     * AJAX handler for saving scorecard
     */
    public function ajax_save_scorecard() {
        check_ajax_referer('eos_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'eos-manager'));
        }

        $scorecard_data = isset($_POST['scorecard_data']) ? wp_unslash($_POST['scorecard_data']) : array();

        if (!is_array($scorecard_data)) {
            wp_send_json_error(__('Invalid data.', 'eos-manager'));
        }

        $sanitized = array();
        foreach ($scorecard_data as $key => $value) {
            $sanitized[sanitize_key($key)] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
        }

        $result = EOS_Scorecard::save_metrics($sanitized);

        wp_send_json($result);
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('EOS_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('EOS_Manager', 'deactivate'));

// Initialize the plugin
add_action('plugins_loaded', array('EOS_Manager', 'get_instance'));

/**
 * Plugin utility functions
 */

/**
 * Get EOS Manager instance
 */
function eos_manager() {
    return EOS_Manager::get_instance();
}

/**
 * Check if user has EOS access
 */
function eos_user_can_access() {
    return current_user_can('manage_options');
}

/**
 * Get EOS data by type
 */
function eos_get_data($type, $args = array()) {
    switch ($type) {
        case 'rocks':
            return EOS_Rocks::get_rocks($args);
        case 'issues':
            return EOS_Issues::get_issues($args);
        case 'scorecard':
            return EOS_Scorecard::get_metrics($args);
        case 'meetings':
            return EOS_Meetings::get_meetings($args);
        case 'people':
            return EOS_People::get_people($args);
        default:
            return false;
    }
}

/**
 * EOS shortcode for frontend display
 */
function eos_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'dashboard',
        'limit' => 10,
        'status' => 'all'
    ), $atts);
    
    if (!eos_user_can_access()) {
        return '<p>' . __('You do not have permission to view EOS data.', 'eos-manager') . '</p>';
    }
    
    ob_start();
    
    switch ($atts['type']) {
        case 'rocks':
            include EOS_MANAGER_PLUGIN_DIR . 'frontend/views/rocks-widget.php';
            break;
        case 'scorecard':
            include EOS_MANAGER_PLUGIN_DIR . 'frontend/views/scorecard-widget.php';
            break;
        case 'dashboard':
        default:
            include EOS_MANAGER_PLUGIN_DIR . 'frontend/views/dashboard-widget.php';
            break;
    }
    
    return ob_get_clean();
}
add_shortcode('eos', 'eos_shortcode');

