<?php
if (!defined('ABSPATH')) {
    exit;
}

$metrics = EOS_Scorecard::get_metrics();
?>
<div class="wrap">
    <h1><?php esc_html_e('Scorecard', 'eos-manager'); ?></h1>
    <p><?php printf(esc_html__('Total Metrics: %d', 'eos-manager'), count($metrics)); ?></p>
</div>

