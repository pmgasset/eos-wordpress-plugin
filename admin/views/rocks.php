<?php
if (!defined('ABSPATH')) {
    exit;
}

$rocks = EOS_Rocks::get_rocks();
?>
<div class="wrap">
    <h1><?php esc_html_e('Rocks', 'eos-manager'); ?></h1>
    <p><?php printf(esc_html__('Total Rocks: %d', 'eos-manager'), count($rocks)); ?></p>
</div>

