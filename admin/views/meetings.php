<?php
if (!defined('ABSPATH')) {
    exit;
}

$meetings = EOS_Meetings::get_meetings();
?>
<div class="wrap">
    <h1><?php esc_html_e('L10 Meetings', 'eos-manager'); ?></h1>
    <p><?php printf(esc_html__('Total Meetings: %d', 'eos-manager'), count($meetings)); ?></p>
</div>

