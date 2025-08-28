<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Google Calendar Integration', 'eos-manager'); ?></h1>
    <p><?php esc_html_e('Connect your Google Calendar to automatically schedule EOS meetings and reminders.', 'eos-manager'); ?></p>

    <form method="post" action="options.php" class="eos-google-settings-form">
        <?php settings_fields('eos_integrations'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="eos_google_client_id"><?php esc_html_e('Client ID', 'eos-manager'); ?></label></th>
                <td><input name="eos_google_client_id" type="text" id="eos_google_client_id" value="<?php echo esc_attr(get_option('eos_google_client_id')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="eos_google_client_secret"><?php esc_html_e('Client Secret', 'eos-manager'); ?></label></th>
                <td><input name="eos_google_client_secret" type="text" id="eos_google_client_secret" value="<?php echo esc_attr(get_option('eos_google_client_secret')); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php submit_button(__('Save Settings', 'eos-manager')); ?>
    </form>

    <h2><?php esc_html_e('Setup Instructions', 'eos-manager'); ?></h2>
    <ol class="eos-google-setup-instructions">
        <li><?php esc_html_e('Visit the Google Cloud Console and create a new project.', 'eos-manager'); ?></li>
        <li><?php esc_html_e('Enable the Google Calendar API for your project.', 'eos-manager'); ?></li>
        <li><?php esc_html_e('Create OAuth credentials and set the redirect URI to your site', 'eos-manager'); ?>:<br />
            <code><?php echo esc_url(admin_url('admin.php?page=eos-google-calendar')); ?></code></li>
        <li><?php esc_html_e('Copy the Client ID and Client Secret into the fields above and save.', 'eos-manager'); ?></li>
        <li><?php esc_html_e('After saving, you will be prompted to authorize the connection.', 'eos-manager'); ?></li>
    </ol>
</div>
