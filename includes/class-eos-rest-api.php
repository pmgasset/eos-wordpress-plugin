<?php
/**
 * EOS Manager REST API Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_REST_API {
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Rocks endpoints
        register_rest_route('eos/v1', '/rocks', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_rocks'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'status' => array(
                        'default' => 'active',
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'owner' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'limit' => array(
                        'default' => 50,
                        'sanitize_callback' => 'absint'
                    )
                )
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_rock'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'title' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'description' => array(
                        'sanitize_callback' => 'wp_kses_post'
                    ),
                    'owner' => array(
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'due_date' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'priority' => array(
                        'default' => 'medium',
                        'enum' => array('high', 'medium', 'low')
                    )
                )
            )
        ));
        
        register_rest_route('eos/v1', '/rocks/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_rock'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => array(__CLASS__, 'validate_numeric_id')
                    )
                )
            ),
            array(
                'methods' => 'PUT',
                'callback' => array(__CLASS__, 'update_rock'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => array(__CLASS__, 'validate_numeric_id')
                    )
                )
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array(__CLASS__, 'delete_rock'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => array(__CLASS__, 'validate_numeric_id')
                    )
                )
            )
        ));
        
        // Issues endpoints
        register_rest_route('eos/v1', '/issues', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_issues'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'status' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'priority' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'limit' => array(
                        'default' => 50,
                        'sanitize_callback' => 'absint'
                    )
                )
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_issue'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'title' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'description' => array(
                        'sanitize_callback' => 'wp_kses_post'
                    ),
                    'priority' => array(
                        'default' => 'medium',
                        'enum' => array('high', 'medium', 'low')
                    ),
                    'assignee' => array(
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            )
        ));
        
        register_rest_route('eos/v1', '/issues/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_issue'),
                'permission_callback' => array(__CLASS__, 'check_permissions')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array(__CLASS__, 'update_issue'),
                'permission_callback' => array(__CLASS__, 'check_permissions')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array(__CLASS__, 'delete_issue'),
                'permission_callback' => array(__CLASS__, 'check_permissions')
            )
        ));
        
        // Scorecard endpoints
        register_rest_route('eos/v1', '/scorecard/metrics', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_metrics'),
                'permission_callback' => array(__CLASS__, 'check_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_metric'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'name' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'goal' => array(
                        'required' => true,
                        'validate_callback' => array(__CLASS__, 'validate_positive_number')
                    ),
                    'owner' => array(
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'frequency' => array(
                        'default' => 'weekly',
                        'enum' => array('daily', 'weekly', 'monthly', 'quarterly')
                    )
                )
            )
        ));
        
        register_rest_route('eos/v1', '/scorecard/metrics/(?P<id>\d+)/value', array(
            'methods' => 'PUT',
            'callback' => array(__CLASS__, 'update_metric_value'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
            'args' => array(
                'value' => array(
                    'required' => true,
                    'validate_callback' => array(__CLASS__, 'validate_number')
                ),
                'week_ending' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Meetings endpoints
        register_rest_route('eos/v1', '/meetings', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_meetings'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'upcoming' => array(
                        'default' => false,
                        'validate_callback' => array(__CLASS__, 'validate_boolean')
                    ),
                    'limit' => array(
                        'default' => 20,
                        'sanitize_callback' => 'absint'
                    )
                )
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_meeting'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'title' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'meeting_date' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'attendees' => array(
                        'sanitize_callback' => 'wp_kses_post'
                    )
                )
            )
        ));
        
        // Dashboard stats endpoint
        register_rest_route('eos/v1', '/dashboard/stats', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_dashboard_stats'),
            'permission_callback' => array(__CLASS__, 'check_permissions')
        ));
        
        // Webhook endpoint for integrations
        register_rest_route('eos/v1', '/webhook/(?P<integration>[a-zA-Z0-9_-]+)', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_webhook'),
            'permission_callback' => array(__CLASS__, 'check_webhook_permissions'),
            'args' => array(
                'integration' => array(
                    'sanitize_callback' => 'sanitize_key'
                )
            )
        ));
    }
    
    /**
     * Rocks API endpoints
     */
    public static function get_rocks($request) {
        $params = $request->get_params();
        $rocks = EOS_Rocks::get_rocks($params);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $rocks,
            'total' => count($rocks)
        ));
    }
    
    public static function get_rock($request) {
        $id = $request['id'];
        $rock = EOS_Rocks::get_rock($id);
        
        if (!$rock) {
            return new WP_Error('rock_not_found', __('Rock not found.', 'eos-manager'), array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $rock
        ));
    }
    
    public static function create_rock($request) {
        $params = $request->get_params();
        $result = EOS_Rocks::save_rock($params);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('rock_creation_failed', $result['message'], array('status' => 400));
        }
    }
    
    public static function update_rock($request) {
        $id = $request['id'];
        $params = $request->get_params();
        $params['id'] = $id;
        
        $result = EOS_Rocks::save_rock($params);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('rock_update_failed', $result['message'], array('status' => 400));
        }
    }
    
    public static function delete_rock($request) {
        $id = $request['id'];
        $result = EOS_Rocks::delete_rock($id);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('rock_deletion_failed', $result['message'], array('status' => 400));
        }
    }
    
    /**
     * Issues API endpoints
     */
    public static function get_issues($request) {
        $params = $request->get_params();
        $issues = EOS_Issues::get_issues($params);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $issues,
            'total' => count($issues)
        ));
    }
    
    public static function get_issue($request) {
        $id = $request['id'];
        $issue = EOS_Issues::get_issue($id);
        
        if (!$issue) {
            return new WP_Error('issue_not_found', __('Issue not found.', 'eos-manager'), array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $issue
        ));
    }
    
    public static function create_issue($request) {
        $params = $request->get_params();
        $result = EOS_Issues::save_issue($params);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('issue_creation_failed', $result['message'], array('status' => 400));
        }
    }
    
    public static function update_issue($request) {
        $id = $request['id'];
        $params = $request->get_params();
        $params['id'] = $id;
        
        $result = EOS_Issues::save_issue($params);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('issue_update_failed', $result['message'], array('status' => 400));
        }
    }
    
    public static function delete_issue($request) {
        $id = $request['id'];
        $result = EOS_Issues::delete_issue($id);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('issue_deletion_failed', $result['message'], array('status' => 400));
        }
    }
    
    /**
     * Scorecard API endpoints
     */
    public static function get_metrics($request) {
        $params = $request->get_params();
        $metrics = EOS_Scorecard::get_metrics($params);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $metrics,
            'total' => count($metrics)
        ));
    }
    
    public static function create_metric($request) {
        $params = $request->get_params();
        $result = EOS_Scorecard::save_metric($params);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('metric_creation_failed', $result['message'], array('status' => 400));
        }
    }
    
    public static function update_metric_value($request) {
        $id = $request['id'];
        $value = $request['value'];
        $week_ending = $request->get_param('week_ending');
        
        $result = EOS_Scorecard::update_metric_value($id, $value, $week_ending);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('metric_update_failed', $result['message'], array('status' => 400));
        }
    }
    
    /**
     * Meetings API endpoints
     */
    public static function get_meetings($request) {
        $params = $request->get_params();
        $meetings = EOS_Meetings::get_meetings($params);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $meetings,
            'total' => count($meetings)
        ));
    }
    
    public static function create_meeting($request) {
        $params = $request->get_params();
        $result = EOS_Meetings::save_meeting($params);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('meeting_creation_failed', $result['message'], array('status' => 400));
        }
    }
    
    /**
     * Dashboard stats endpoint
     */
    public static function get_dashboard_stats($request) {
        $stats = array(
            'rocks' => EOS_Rocks::get_stats(),
            'issues' => EOS_Issues::get_stats(),
            'scorecard' => EOS_Scorecard::get_stats(),
            'meetings' => EOS_Meetings::get_stats()
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $stats
        ));
    }
    
    /**
     * Webhook handler for integrations
     */
    public static function handle_webhook($request) {
        $integration = $request['integration'];
        $data = $request->get_json_params();
        
        switch ($integration) {
            case 'gohighlevel':
                return self::handle_gohighlevel_webhook($data);
            case 'zapier':
                return self::handle_zapier_webhook($data);
            case 'slack':
                return self::handle_slack_webhook($data);
            default:
                return new WP_Error('unknown_integration', __('Unknown integration.', 'eos-manager'), array('status' => 400));
        }
    }
    
    /**
     * Handle GoHighLevel webhook
     */
    private static function handle_gohighlevel_webhook($data) {
        // Process GoHighLevel data
        if (isset($data['contact']) && !empty($data['contact']['tags'])) {
            $tags = $data['contact']['tags'];
            
            // Only process if contact has EOS tag
            if (in_array('EOS', $tags) || in_array('eos', $tags)) {
                // Create or update contact in EOS system
                $contact_data = array(
                    'name' => $data['contact']['firstName'] . ' ' . $data['contact']['lastName'],
                    'email' => $data['contact']['email'],
                    'phone' => $data['contact']['phone'] ?? '',
                    'company' => $data['contact']['companyName'] ?? '',
                    'source' => 'gohighlevel',
                    'external_id' => $data['contact']['id']
                );
                
                // This would be handled by a contacts module
                do_action('eos_process_external_contact', $contact_data);
                
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Contact processed successfully'
                ));
            }
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Webhook received but no action taken'
        ));
    }
    
    /**
     * Handle Zapier webhook
     */
    private static function handle_zapier_webhook($data) {
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'create_rock':
                if (isset($data['rock'])) {
                    $result = EOS_Rocks::save_rock($data['rock']);
                    return rest_ensure_response($result);
                }
                break;
            case 'create_issue':
                if (isset($data['issue'])) {
                    $result = EOS_Issues::save_issue($data['issue']);
                    return rest_ensure_response($result);
                }
                break;
            case 'update_scorecard':
                if (isset($data['metrics'])) {
                    $result = EOS_Scorecard::save_scorecard_data($data);
                    return rest_ensure_response($result);
                }
                break;
        }
        
        return new WP_Error('invalid_zapier_action', __('Invalid or missing action.', 'eos-manager'), array('status' => 400));
    }
    
    /**
     * Handle Slack webhook
     */
    private static function handle_slack_webhook($data) {
        // Process Slack slash commands or interactive components
        $command = $data['command'] ?? '';
        $text = $data['text'] ?? '';
        
        switch ($command) {
            case '/eos-rock':
                return self::handle_slack_rock_command($text, $data);
            case '/eos-issue':
                return self::handle_slack_issue_command($text, $data);
            case '/eos-scorecard':
                return self::handle_slack_scorecard_command($text, $data);
        }
        
        return rest_ensure_response(array(
            'text' => 'Unknown EOS command. Use /eos-rock, /eos-issue, or /eos-scorecard',
            'response_type' => 'ephemeral'
        ));
    }
    
    /**
     * Handle Slack rock command
     */
    private static function handle_slack_rock_command($text, $data) {
        $parts = explode(' ', $text, 2);
        $action = $parts[0] ?? '';
        
        switch ($action) {
            case 'list':
                $rocks = EOS_Rocks::get_rocks(array('limit' => 5));
                $text = "Current Rocks:\n";
                foreach ($rocks as $rock) {
                    $text .= "• {$rock['title']} ({$rock['progress']}%) - {$rock['owner']}\n";
                }
                break;
            case 'add':
                $rock_title = $parts[1] ?? '';
                if ($rock_title) {
                    $result = EOS_Rocks::save_rock(array(
                        'title' => $rock_title,
                        'owner' => $data['user_name'],
                        'due_date' => date('Y-m-d', strtotime('+90 days')),
                        'priority' => 'medium'
                    ));
                    $text = $result['success'] ? "Rock '{$rock_title}' created!" : "Failed to create rock.";
                } else {
                    $text = "Usage: /eos-rock add [rock title]";
                }
                break;
            default:
                $text = "Usage: /eos-rock [list|add] [title]";
        }
        
        return rest_ensure_response(array(
            'text' => $text,
            'response_type' => 'ephemeral'
        ));
    }
    
    /**
     * Permission and validation callbacks
     */
    public static function check_permissions($request) {
        return current_user_can('manage_options');
    }
    
    public static function check_webhook_permissions($request) {
        // For webhooks, we might use API keys or other authentication
        $api_key = $request->get_header('X-EOS-API-Key');
        $stored_key = get_option('eos_api_key');
        
        if (empty($stored_key)) {
            // Generate API key if none exists
            $stored_key = wp_generate_password(32, false);
            update_option('eos_api_key', $stored_key);
        }
        
        return !empty($api_key) && hash_equals($stored_key, $api_key);
    }
    
    public static function validate_numeric_id($param, $request, $key) {
        return is_numeric($param) && $param > 0;
    }
    
    public static function validate_positive_number($param, $request, $key) {
        return is_numeric($param) && $param > 0;
    }
    
    public static function validate_number($param, $request, $key) {
        return is_numeric($param);
    }
    
    public static function validate_boolean($param, $request, $key) {
        return is_bool($param) || in_array(strtolower($param), array('true', 'false', '1', '0'));
    }
    
    /**
     * Get API documentation
     */
    public static function get_api_documentation() {
        return array(
            'version' => '1.0',
            'base_url' => rest_url('eos/v1/'),
            'authentication' => 'WordPress authentication or API key for webhooks',
            'endpoints' => array(
                'rocks' => array(
                    'GET /rocks' => 'Get all rocks',
                    'POST /rocks' => 'Create a new rock',
                    'GET /rocks/{id}' => 'Get specific rock',
                    'PUT /rocks/{id}' => 'Update specific rock',
                    'DELETE /rocks/{id}' => 'Delete specific rock'
                ),
                'issues' => array(
                    'GET /issues' => 'Get all issues',
                    'POST /issues' => 'Create a new issue',
                    'GET /issues/{id}' => 'Get specific issue',
                    'PUT /issues/{id}' => 'Update specific issue',
                    'DELETE /issues/{id}' => 'Delete specific issue'
                ),
                'scorecard' => array(
                    'GET /scorecard/metrics' => 'Get all metrics',
                    'POST /scorecard/metrics' => 'Create a new metric',
                    'PUT /scorecard/metrics/{id}/value' => 'Update metric value'
                ),
                'meetings' => array(
                    'GET /meetings' => 'Get all meetings',
                    'POST /meetings' => 'Create a new meeting'
                ),
                'dashboard' => array(
                    'GET /dashboard/stats' => 'Get dashboard statistics'
                ),
                'webhooks' => array(
                    'POST /webhook/{integration}' => 'Handle webhook from external service'
                )
            )
        );
    }
}
