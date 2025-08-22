<?php
/**
 * EOS Database Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Database {
    
    /**
     * Create all EOS database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Rocks table
        $table_rocks = $wpdb->prefix . 'eos_rocks';
        $sql_rocks = "CREATE TABLE $table_rocks (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            owner varchar(100),
            due_date date,
            priority enum('high','medium','low') DEFAULT 'medium',
            progress int(3) DEFAULT 0,
            status enum('active','completed','on-hold','cancelled') DEFAULT 'active',
            quarter varchar(10),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20),
            PRIMARY KEY (id),
            KEY owner (owner),
            KEY status (status),
            KEY quarter (quarter),
            KEY due_date (due_date)
        ) $charset_collate;";
        
        // Issues table
        $table_issues = $wpdb->prefix . 'eos_issues';
        $sql_issues = "CREATE TABLE $table_issues (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            priority enum('high','medium','low') DEFAULT 'medium',
            status enum('identified','discussing','solved','closed') DEFAULT 'identified',
            assignee varchar(100),
            related_rock_id mediumint(9),
            category varchar(50) DEFAULT 'general',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            created_by bigint(20),
            PRIMARY KEY (id),
            KEY priority (priority),
            KEY status (status),
            KEY assignee (assignee),
            KEY category (category),
            KEY related_rock_id (related_rock_id)
        ) $charset_collate;";
        
        // Scorecard metrics table
        $table_scorecard = $wpdb->prefix . 'eos_scorecard_metrics';
        $sql_scorecard = "CREATE TABLE $table_scorecard (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            goal decimal(10,2),
            current_value decimal(10,2),
            status enum('on-track','at-risk','behind','above-target') DEFAULT 'on-track',
            owner varchar(100),
            frequency enum('daily','weekly','monthly','quarterly') DEFAULT 'weekly',
            unit varchar(50),
            is_active boolean DEFAULT TRUE,
            sort_order int(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner (owner),
            KEY status (status),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Scorecard data (weekly/daily entries)
        $table_scorecard_data = $wpdb->prefix . 'eos_scorecard_data';
        $sql_scorecard_data = "CREATE TABLE $table_scorecard_data (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            metric_id mediumint(9) NOT NULL,
            value decimal(10,2),
            week_ending date,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20),
            PRIMARY KEY (id),
            KEY metric_id (metric_id),
            KEY week_ending (week_ending),
            UNIQUE KEY unique_metric_week (metric_id, week_ending),
            FOREIGN KEY (metric_id) REFERENCES $table_scorecard(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // People table
        $table_people = $wpdb->prefix . 'eos_people';
        $sql_people = "CREATE TABLE $table_people (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            role varchar(255),
            seat varchar(255),
            department varchar(100),
            get_it boolean DEFAULT FALSE,
            want_it boolean DEFAULT FALSE,
            capacity boolean DEFAULT FALSE,
            status enum('right-person-right-seat','right-person-wrong-seat','wrong-person-right-seat','wrong-person-wrong-seat') DEFAULT 'right-person-right-seat',
            email varchar(255),
            start_date date,
            is_active boolean DEFAULT TRUE,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY department (department),
            KEY status (status),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // L10 Meetings table
        $table_meetings = $wpdb->prefix . 'eos_meetings';
        $sql_meetings = "CREATE TABLE $table_meetings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            meeting_date datetime NOT NULL,
            duration int(3) DEFAULT 90,
            attendees text,
            agenda_notes text,
            segue_notes text,
            scorecard_notes text,
            rock_review_notes text,
            headlines_notes text,
            todo_review_notes text,
            ids_notes text,
            conclusion_notes text,
            action_items text,
            google_meet_link varchar(500),
            status enum('scheduled','in-progress','completed','cancelled') DEFAULT 'scheduled',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20),
            PRIMARY KEY (id),
            KEY meeting_date (meeting_date),
            KEY status (status)
        ) $charset_collate;";
        
        // To-Do items table
        $table_todos = $wpdb->prefix . 'eos_todos';
        $sql_todos = "CREATE TABLE $table_todos (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            assignee varchar(100),
            due_date date,
            status enum('pending','completed','overdue') DEFAULT 'pending',
            meeting_id mediumint(9),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            created_by bigint(20),
            PRIMARY KEY (id),
            KEY assignee (assignee),
            KEY status (status),
            KEY due_date (due_date),
            KEY meeting_id (meeting_id)
        ) $charset_collate;";
        
        // VTO (Vision/Traction Organizer) table
        $table_vto = $wpdb->prefix . 'eos_vto';
        $sql_vto = "CREATE TABLE $table_vto (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            section enum('core-values','core-focus','10-year-target','marketing-strategy','3-year-picture','1-year-plan','quarterly-rocks','issues-list') NOT NULL,
            content text,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by bigint(20),
            PRIMARY KEY (id),
            UNIQUE KEY section (section)
        ) $charset_collate;";
        
        // Integration settings table
        $table_integrations = $wpdb->prefix . 'eos_integrations';
        $sql_integrations = "CREATE TABLE $table_integrations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            integration_type varchar(50) NOT NULL,
            settings text,
            is_active boolean DEFAULT FALSE,
            last_sync datetime NULL,
            sync_status enum('success','error','pending') DEFAULT 'pending',
            sync_log text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY integration_type (integration_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Execute table creation
        dbDelta($sql_rocks);
        dbDelta($sql_issues);
        dbDelta($sql_scorecard);
        dbDelta($sql_scorecard_data);
        dbDelta($sql_people);
        dbDelta($sql_meetings);
        dbDelta($sql_todos);
        dbDelta($sql_vto);
        dbDelta($sql_integrations);
        
        // Create default VTO sections
        self::create_default_vto_sections();
        
        // Update database version
        update_option('eos_manager_db_version', '1.0');
    }
    
    /**
     * Create default VTO sections
     */
    private static function create_default_vto_sections() {
        global $wpdb;
        
        $table_vto = $wpdb->prefix . 'eos_vto';
        
        $default_sections = array(
            'core-values' => '',
            'core-focus' => '',
            '10-year-target' => '',
            'marketing-strategy' => '',
            '3-year-picture' => '',
            '1-year-plan' => '',
            'quarterly-rocks' => '',
            'issues-list' => ''
        );
        
        foreach ($default_sections as $section => $content) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_vto WHERE section = %s", $section));
            if (!$exists) {
                $wpdb->insert(
                    $table_vto,
                    array(
                        'section' => $section,
                        'content' => $content,
                        'updated_by' => get_current_user_id()
                    ),
                    array('%s', '%s', '%d')
                );
            }
        }
    }
    
    /**
     * Drop all EOS tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'eos_scorecard_data',
            $wpdb->prefix . 'eos_todos',
            $wpdb->prefix . 'eos_meetings',
            $wpdb->prefix . 'eos_people',
            $wpdb->prefix . 'eos_scorecard_metrics',
            $wpdb->prefix . 'eos_issues',
            $wpdb->prefix . 'eos_rocks',
            $wpdb->prefix . 'eos_vto',
            $wpdb->prefix . 'eos_integrations'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Clean up options
        delete_option('eos_manager_version');
        delete_option('eos_manager_db_version');
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'eos_' . $table;
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $table_rocks = $wpdb->prefix . 'eos_rocks';
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_rocks'");
        
        return $result === $table_rocks;
    }
    
    /**
     * Update database schema if needed
     */
    public static function maybe_update_db() {
        $current_db_version = get_option('eos_manager_db_version', '0');
        
        if (version_compare($current_db_version, '1.0', '<')) {
            self::create_tables();
        }
    }
}

?>