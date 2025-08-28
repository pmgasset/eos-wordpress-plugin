<?php
if (!defined('ABSPATH')) {
    exit;
}

$metrics = EOS_Scorecard::get_metrics();
?>
<div class="wrap eos-scorecard">
    <h1><?php esc_html_e('Company Scorecard', 'eos-manager'); ?></h1>

    <table class="widefat eos-scorecard-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Metric', 'eos-manager'); ?></th>
                <th><?php esc_html_e('Goal', 'eos-manager'); ?></th>
                <th><?php esc_html_e('Current Week', 'eos-manager'); ?></th>
                <th><?php esc_html_e('Owner', 'eos-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'eos-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($metrics)) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No metrics found. Add one below to get started.', 'eos-manager'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($metrics as $metric) : ?>
                    <tr>
                        <td><?php echo esc_html($metric['name']); ?></td>
                        <td><?php echo esc_html($metric['goal_formatted']); ?></td>
                        <td>
                            <input type="number" step="any" id="metric-value-<?php echo intval($metric['id']); ?>" value="<?php echo esc_attr($metric['current_value']); ?>" />
                        </td>
                        <td><?php echo esc_html($metric['owner']); ?></td>
                        <td>
                            <button type="button" class="button" onclick="updateMetric(<?php echo intval($metric['id']); ?>)">
                                <?php esc_html_e('Update', 'eos-manager'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2><?php esc_html_e('Add Metric', 'eos-manager'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="metricName"><?php esc_html_e('Name', 'eos-manager'); ?></label></th>
            <td><input type="text" id="metricName" class="regular-text" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="metricGoal"><?php esc_html_e('Goal', 'eos-manager'); ?></label></th>
            <td><input type="number" step="any" id="metricGoal" class="regular-text" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="metricOwner"><?php esc_html_e('Owner', 'eos-manager'); ?></label></th>
            <td><input type="text" id="metricOwner" class="regular-text" /></td>
        </tr>
    </table>
    <p>
        <button type="button" class="button button-primary" onclick="addMetric()">
            <?php esc_html_e('Save Metric', 'eos-manager'); ?>
        </button>
    </p>
</div>

<script>
function addMetric() {
    const metricData = {
        name: jQuery('#metricName').val(),
        goal: jQuery('#metricGoal').val(),
        owner: jQuery('#metricOwner').val()
    };

    jQuery.post(ajaxurl, {
        action: 'eos_save_metric',
        nonce: '<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',
        metric_data: metricData
    }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert('<?php _e('Error saving metric.', 'eos-manager'); ?>');
        }
    });
}

function updateMetric(id) {
    const value = jQuery('#metric-value-' + id).val();
    jQuery.post(ajaxurl, {
        action: 'eos_update_metric_value',
        nonce: '<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',
        metric_id: id,
        value: value
    }, function(response) {
        if (!response.success) {
            alert('<?php _e('Error updating metric.', 'eos-manager'); ?>');
        }
    });
}
</script>
