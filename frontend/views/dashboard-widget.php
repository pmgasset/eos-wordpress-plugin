<?php
/**
 * EOS Dashboard Widget for Frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

$rocks_stats = EOS_Rocks::get_stats();
$issues_stats = EOS_Issues::get_stats();
$scorecard_stats = EOS_Scorecard::get_stats();
?>

<div class="eos-widget eos-dashboard-widget">
    <h3><?php _e('EOS Dashboard', 'eos-manager'); ?></h3>
    
    <div class="eos-stat-grid">
        <div class="eos-stat-item">
            <span class="eos-stat-number"><?php echo $rocks_stats['on_track']; ?></span>
            <span class="eos-stat-label"><?php _e('Rocks On Track', 'eos-manager'); ?></span>
        </div>
        
        <div class="eos-stat-item">
            <span class="eos-stat-number"><?php echo $issues_stats['total_open']; ?></span>
            <span class="eos-stat-label"><?php _e('Open Issues', 'eos-manager'); ?></span>
        </div>
        
        <div class="eos-stat-item">
            <span class="eos-stat-number"><?php echo $scorecard_stats['health_percentage']; ?>%</span>
            <span class="eos-stat-label"><?php _e('Scorecard Health', 'eos-manager'); ?></span>
        </div>
        
        <div class="eos-stat-item">
            <span class="eos-stat-number"><?php echo $scorecard_stats['on_target']; ?>/<?php echo $scorecard_stats['active']; ?></span>
            <span class="eos-stat-label"><?php _e('Metrics On Target', 'eos-manager'); ?></span>
        </div>
    </div>
</div>