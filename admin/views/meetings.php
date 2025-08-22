<?php
if (!defined('ABSPATH')) {
    exit;
}

$meetings = EOS_Meetings::get_meetings();
?>
<div class="wrap">
    <h1><?php esc_html_e('L10 Meetings', 'eos-manager'); ?></h1>
    <p><?php printf(esc_html__('Total Meetings: %d', 'eos-manager'), count($meetings)); ?></p>

    <h2><?php esc_html_e('Google Calendar Integration', 'eos-manager'); ?></h2>
    <form method="post" action="options.php">
        <?php settings_fields('eos_integrations'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="eos_google_client_id"><?php esc_html_e('Client ID', 'eos-manager'); ?></label></th>
                <td><input name="eos_google_client_id" type="text" id="eos_google_client_id" value="<?php echo esc_attr(get_option('eos_google_client_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="eos_google_client_secret"><?php esc_html_e('Client Secret', 'eos-manager'); ?></label></th>
                <td><input name="eos_google_client_secret" type="text" id="eos_google_client_secret" value="<?php echo esc_attr(get_option('eos_google_client_secret')); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>

    <?php $auth_url = EOS_Integrations::get_google_auth_url(); if ($auth_url): ?>
        <p><a href="<?php echo esc_url($auth_url); ?>" class="button"><?php esc_html_e('Authorize Google Calendar', 'eos-manager'); ?></a></p>
    <?php endif; ?>
</div>

