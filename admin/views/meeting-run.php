<?php
if (!defined('ABSPATH')) {
    exit;
}

$meeting_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$meeting = EOS_Meetings::get_meeting($meeting_id);
$agenda = EOS_Meetings::get_l10_agenda_structure();
?>
<div class="wrap eos-meeting-run">
    <?php if ($meeting): ?>
    <h1><?php echo esc_html($meeting['title']); ?></h1>
    <?php if (!empty($meeting['google_meet_link'])): ?>
        <p><a href="<?php echo esc_url($meeting['google_meet_link']); ?>" target="_blank" class="button"><?php esc_html_e('Join Google Meet', 'eos-manager'); ?></a></p>
    <?php endif; ?>

    <?php foreach ($agenda as $item):
        $section = $item['section'];
        $field = $section . '_notes';
        $notes = isset($meeting[$field]) ? $meeting[$field] : '';
    ?>
    <h2><?php echo esc_html($item['title']); ?> (<?php echo intval($item['duration']); ?> <?php esc_html_e('min', 'eos-manager'); ?>)</h2>
    <p><?php echo esc_html($item['description']); ?></p>
    <textarea class="agenda-notes" data-meeting-id="<?php echo $meeting_id; ?>" data-section="<?php echo esc_attr($section); ?>" rows="4" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
    <?php endforeach; ?>

    <h2><?php esc_html_e('Action Items', 'eos-manager'); ?></h2>
    <textarea id="meetingActionItems" rows="4" style="width:100%;"></textarea>
    <p><button class="button button-primary complete-meeting-btn" data-meeting-id="<?php echo $meeting_id; ?>"><?php esc_html_e('Complete Meeting', 'eos-manager'); ?></button></p>
    <?php else: ?>
    <p><?php esc_html_e('Meeting not found.', 'eos-manager'); ?></p>
    <?php endif; ?>
</div>
