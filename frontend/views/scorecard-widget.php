<?php
/**
 * EOS Scorecard Widget for Frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

$metrics = EOS_Scorecard::get_metrics(array('limit' => $atts['limit'], 'is_active' => true));
?>

<div class="eos-widget eos-scorecard-widget">
    <h3><?php _e('Company Scorecard', 'eos-manager'); ?></h3>
    
    <?php if (!empty($metrics)): ?>
        <?php foreach ($metrics as $metric): ?>
            <div class="eos-metric-item" style="margin-bottom: 12px; padding: 12px; background: #f9f9f9; border-radius: 6px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600;"><?php echo esc_html($metric['name']); ?></span>
                    <div style="text-align: right;">
                        <div style="font-size: 14px; font-weight: bold; color: <?php echo $metric['status'] === 'on-track' ? '#4caf50' : ($metric['status'] === 'behind' ? '#f44336' : '#ff9800'); ?>;">
                            <?php echo esc_html($metric['current_value_formatted']); ?>
                        </div>
                        <div style="font-size: 11px; color: #666;">
                            <?php _e('Goal:', 'eos-manager'); ?> <?php echo esc_html($metric['goal_formatted']); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p><?php _e('No scorecard metrics found.', 'eos-manager'); ?></p>
    <?php endif; ?>
</div>