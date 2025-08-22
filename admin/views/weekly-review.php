<?php
if (!defined('ABSPATH')) {
    exit;
}

$score_stats = EOS_Scorecard::get_stats();
$rocks = EOS_Rocks::get_rocks(array('status' => 'active'));
global $wpdb;
$todo_table = EOS_Database::get_table_name('todos');
$todos = $wpdb->get_results("SELECT * FROM $todo_table WHERE status != 'completed' ORDER BY due_date ASC", ARRAY_A);
?>
<div class="wrap">
    <h1><?php esc_html_e('Weekly Review', 'eos-manager'); ?></h1>
    <ol class="eos-weekly-steps">
        <li>
            <h2><?php _e('Scorecard Metrics', 'eos-manager'); ?></h2>
            <p class="<?php echo $score_stats['behind'] > 0 ? 'metric-alert' : ''; ?>">
                <?php printf(__('Behind metrics: %d', 'eos-manager'), intval($score_stats['behind'])); ?>
            </p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=eos-scorecard')); ?>" class="button"><?php _e('Update Scorecard', 'eos-manager'); ?></a>
        </li>
        <li>
            <h2><?php _e('Rock Check-ins', 'eos-manager'); ?></h2>
            <?php if ($rocks) : ?>
                <ul>
                    <?php foreach ($rocks as $rock) : ?>
                        <li><?php echo esc_html($rock['title']); ?> - <?php echo esc_html(ucfirst($rock['status'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php _e('No active rocks.', 'eos-manager'); ?></p>
            <?php endif; ?>
        </li>
        <li>
            <h2><?php _e('To-Do Review', 'eos-manager'); ?></h2>
            <?php if ($todos) : ?>
                <ul>
                    <?php foreach ($todos as $todo) : ?>
                        <li><?php echo esc_html($todo['title']); ?> (<?php echo esc_html($todo['assignee']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php _e('No open to-dos!', 'eos-manager'); ?></p>
            <?php endif; ?>
        </li>
    </ol>
    <button class="button button-primary" id="completeReview"><?php _e('Mark Review Complete', 'eos-manager'); ?></button>
    <p id="reviewMessage"></p>
</div>

<script>
jQuery('#completeReview').on('click', function(){
    jQuery.post(ajaxurl,{action:'eos_complete_weekly_review',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>'},function(res){
        if(res.success){
            jQuery('#reviewMessage').text('<?php echo esc_js(__('Weekly review completed!', 'eos-manager')); ?>').css('color','green');
        }else{
            jQuery('#reviewMessage').text('<?php echo esc_js(__('Could not log review.', 'eos-manager')); ?>').css('color','red');
        }
    });
});
</script>

<style>
.metric-alert{color:#dc3232;font-weight:bold;}
</style>

