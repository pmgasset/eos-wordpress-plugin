<?php
/**
 * EOS Rocks Widget for Frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

$rocks = EOS_Rocks::get_rocks(array('limit' => $atts['limit'], 'status' => 'active'));
?>

<div class="eos-widget eos-rocks-widget">
    <h3><?php _e('Current Rocks', 'eos-manager'); ?></h3>
    
    <?php if (!empty($rocks)): ?>
        <?php foreach ($rocks as $rock): ?>
            <div class="eos-rock-item" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong><?php echo esc_html($rock['title']); ?></strong>
                    <span style="font-size: 12px; color: #666;"><?php echo esc_html($rock['owner']); ?></span>
                </div>
                
                <div class="eos-progress-bar">
                    <div class="eos-progress-fill" data-progress="<?php echo $rock['progress']; ?>" style="width: 0%;"></div>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #666; margin-top: 5px;">
                    <span><?php echo $rock['progress']; ?>% Complete</span>
                    <span><?php _e('Due:', 'eos-manager'); ?> <?php echo esc_html($rock['due_date_formatted']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p><?php _e('No active rocks found.', 'eos-manager'); ?></p>
    <?php endif; ?>
</div>