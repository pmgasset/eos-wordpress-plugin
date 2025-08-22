<?php
/**
 * EOS Integrations Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EOS_Integrations {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'maybe_handle_google_oauth'));
    }
    
    public function init() {
        $this->init_gohighlevel();
        $this->init_google_calendar();
        $this->init_slack();
    }
    
    private function init_gohighlevel() {
        $api_key = get_option('eos_ghl_api_key');
        if (!empty($api_key)) {
            // Initialize GoHighLevel integration
        }
    }
    
    private function init_google_calendar() {
        // Placeholder for future initialization
    }

    public function maybe_handle_google_oauth() {
        if (isset($_GET['eos_google_oauth']) && isset($_GET['code'])) {
            self::handle_google_oauth_callback(sanitize_text_field($_GET['code']));
        }
    }

    /**
     * Get Google OAuth2 authorization URL
     */
    public static function get_google_auth_url() {
        $client_id = get_option('eos_google_client_id');
        if (empty($client_id)) {
            return '';
        }

        $redirect = urlencode(admin_url('admin.php?page=eos-meetings&eos_google_oauth=1'));
        $scope = urlencode('https://www.googleapis.com/auth/calendar.events');
        return 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&access_type=offline&prompt=consent'
            . '&client_id=' . rawurlencode($client_id)
            . '&redirect_uri=' . $redirect
            . '&scope=' . $scope;
    }

    /**
     * Handle OAuth callback and store tokens
     */
    public static function handle_google_oauth_callback($code) {
        $client_id = get_option('eos_google_client_id');
        $client_secret = get_option('eos_google_client_secret');

        if (empty($client_id) || empty($client_secret)) {
            return false;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => admin_url('admin.php?page=eos-meetings&eos_google_oauth=1'),
                'grant_type' => 'authorization_code'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['access_token'])) {
            update_option('eos_google_access_token', $body['access_token']);
            if (!empty($body['refresh_token'])) {
                update_option('eos_google_refresh_token', $body['refresh_token']);
            }
            return true;
        }

        return false;
    }

    /**
     * Create Google Calendar event with Meet link
     */
    public static function create_google_meet_event($meeting_data) {
        $access_token = get_option('eos_google_access_token');
        if (empty($access_token)) {
            return '';
        }

        $start = isset($meeting_data['meeting_date']) ? $meeting_data['meeting_date'] : current_time('mysql');
        $duration = isset($meeting_data['duration']) ? intval($meeting_data['duration']) : 60;
        $end = date('Y-m-d\TH:i:sP', strtotime($start) + ($duration * 60));
        $start = date('Y-m-d\TH:i:sP', strtotime($start));

        $event = array(
            'summary' => $meeting_data['title'],
            'start' => array('dateTime' => $start),
            'end' => array('dateTime' => $end),
            'conferenceData' => array(
                'createRequest' => array(
                    'requestId' => uniqid()
                )
            )
        );

        $response = wp_remote_post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode($event)
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['hangoutLink']) ? $body['hangoutLink'] : '';
    }
    
    private function init_slack() {
        $webhook_url = get_option('eos_slack_webhook');
        if (!empty($webhook_url)) {
            // Initialize Slack integration
        }
    }
    
    public static function send_slack_notification($message, $channel = null) {
        $webhook_url = get_option('eos_slack_webhook');
        if (empty($webhook_url)) {
            return false;
        }
        
        $payload = array(
            'text' => $message,
            'username' => 'EOS Manager'
        );
        
        if ($channel) {
            $payload['channel'] = $channel;
        }
        
        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($payload),
            'headers' => array('Content-Type' => 'application/json')
        ));
        
        return !is_wp_error($response);
    }
}
