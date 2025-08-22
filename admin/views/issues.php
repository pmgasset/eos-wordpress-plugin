<?php
if (!defined('ABSPATH')) {
    exit;
}

$issues = EOS_Issues::get_issues();
?>
<div class="wrap">
    <h1><?php esc_html_e('Issues', 'eos-manager'); ?></h1>
    <p><?php printf(esc_html__('Total Issues: %d', 'eos-manager'), count($issues)); ?></p>
</div>

