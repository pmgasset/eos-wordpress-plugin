<?php
if (!defined('ABSPATH')) {
    exit;
}

$status    = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$priority  = isset($_GET['priority']) ? sanitize_key($_GET['priority']) : '';
$assignee  = isset($_GET['assignee']) ? sanitize_text_field($_GET['assignee']) : '';
$rock_id   = isset($_GET['related_rock_id']) ? intval($_GET['related_rock_id']) : 0;

$args = array();
if (!empty($status)) {
    $args['status'] = $status;
}
if (!empty($priority)) {
    $args['priority'] = $priority;
}
if (!empty($assignee)) {
    $args['assignee'] = $assignee;
}
if (!empty($rock_id)) {
    $args['related_rock_id'] = $rock_id;
}

$issues = EOS_Issues::get_issues($args);
$stats  = EOS_Issues::get_stats();
$rocks  = EOS_Rocks::get_rocks(array('limit' => 100));
$people = EOS_People::get_people(array('limit' => 100));
?>
<div class="wrap">
    <h1><?php esc_html_e('Issues', 'eos-manager'); ?></h1>
    <div class="eos-issue-stats">
        <span><?php esc_html_e('Open Issues:', 'eos-manager'); ?> <strong id="totalOpen"><?php echo esc_html($stats['total_open']); ?></strong></span>
        <span><?php esc_html_e('High Priority:', 'eos-manager'); ?> <strong id="highPriority"><?php echo esc_html($stats['high_priority']); ?></strong></span>
    </div>

    <form method="get" class="eos-issue-filters">
        <input type="hidden" name="page" value="eos-issues" />
        <select name="status">
            <option value=""><?php _e('All Statuses', 'eos-manager'); ?></option>
            <option value="identified" <?php selected($status, 'identified'); ?>><?php _e('Identified', 'eos-manager'); ?></option>
            <option value="discussing" <?php selected($status, 'discussing'); ?>><?php _e('Discussing', 'eos-manager'); ?></option>
            <option value="solved" <?php selected($status, 'solved'); ?>><?php _e('Solved', 'eos-manager'); ?></option>
            <option value="closed" <?php selected($status, 'closed'); ?>><?php _e('Closed', 'eos-manager'); ?></option>
        </select>
        <select name="priority">
            <option value=""><?php _e('All Priorities', 'eos-manager'); ?></option>
            <option value="high" <?php selected($priority, 'high'); ?>><?php _e('High', 'eos-manager'); ?></option>
            <option value="medium" <?php selected($priority, 'medium'); ?>><?php _e('Medium', 'eos-manager'); ?></option>
            <option value="low" <?php selected($priority, 'low'); ?>><?php _e('Low', 'eos-manager'); ?></option>
        </select>
        <select name="assignee">
            <option value=""><?php _e('All Assignees', 'eos-manager'); ?></option>
            <?php foreach ($people as $p) : ?>
                <option value="<?php echo esc_attr($p['name']); ?>" <?php selected($assignee, $p['name']); ?>><?php echo esc_html($p['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="related_rock_id">
            <option value="0"><?php _e('All Rocks', 'eos-manager'); ?></option>
            <?php foreach ($rocks as $rock) : ?>
                <option value="<?php echo intval($rock['id']); ?>" <?php selected($rock_id, $rock['id']); ?>><?php echo esc_html($rock['title']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button"><?php _e('Filter', 'eos-manager'); ?></button>
        <button type="button" class="button button-primary" onclick="openIssueModal();"><?php _e('Add Issue', 'eos-manager'); ?></button>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Title', 'eos-manager'); ?></th>
                <th><?php _e('Priority', 'eos-manager'); ?></th>
                <th><?php _e('Assignee', 'eos-manager'); ?></th>
                <th><?php _e('Status', 'eos-manager'); ?></th>
                <th><?php _e('Actions', 'eos-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($issues)) : ?>
                <?php foreach ($issues as $issue) : ?>
                    <tr data-id="<?php echo intval($issue['id']); ?>"
                        data-title="<?php echo esc_attr($issue['title']); ?>"
                        data-description="<?php echo esc_attr($issue['description']); ?>"
                        data-priority="<?php echo esc_attr($issue['priority']); ?>"
                        data-assignee="<?php echo esc_attr($issue['assignee']); ?>"
                        data-status="<?php echo esc_attr($issue['status']); ?>"
                        data-rock="<?php echo intval($issue['related_rock_id']); ?>">
                        <td><?php echo esc_html($issue['title']); ?></td>
                        <td><span class="issue-priority <?php echo esc_attr($issue['priority']); ?>"><?php echo esc_html(ucfirst($issue['priority'])); ?></span></td>
                        <td><?php echo esc_html($issue['assignee']); ?></td>
                        <td><span class="issue-status <?php echo esc_attr($issue['status']); ?>"><?php echo esc_html(ucfirst($issue['status'])); ?></span></td>
                        <td>
                            <a href="#" class="issue-action discuss"><?php _e('Discuss', 'eos-manager'); ?></a> |
                            <a href="#" class="issue-action solve"><?php _e('Solve', 'eos-manager'); ?></a> |
                            <a href="#" class="issue-action assign"><?php _e('Assign', 'eos-manager'); ?></a> |
                            <a href="#" class="issue-action delete" style="color:red;">&times;</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="5"><?php _e('No issues found.', 'eos-manager'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="issueModal" class="eos-modal">
    <div class="eos-modal-content">
        <div class="eos-modal-header">
            <h2 id="issueModalTitle"><?php _e('Add Issue', 'eos-manager'); ?></h2>
            <button class="eos-modal-close" onclick="closeIssueModal()">&times;</button>
        </div>
        <form id="issueForm">
            <input type="hidden" id="issueId" />
            <div class="eos-form-field">
                <label><?php _e('Title', 'eos-manager'); ?></label>
                <input type="text" id="issueTitle" required />
            </div>
            <div class="eos-form-field">
                <label><?php _e('Description', 'eos-manager'); ?></label>
                <textarea id="issueDescription"></textarea>
            </div>
            <div class="eos-form-row">
                <div class="eos-form-field">
                    <label><?php _e('Priority', 'eos-manager'); ?></label>
                    <select id="issuePriority">
                        <option value="high"><?php _e('High', 'eos-manager'); ?></option>
                        <option value="medium"><?php _e('Medium', 'eos-manager'); ?></option>
                        <option value="low"><?php _e('Low', 'eos-manager'); ?></option>
                    </select>
                </div>
                <div class="eos-form-field">
                    <label><?php _e('Assignee', 'eos-manager'); ?></label>
                    <select id="issueAssignee">
                        <option value=""><?php _e('Unassigned', 'eos-manager'); ?></option>
                        <?php foreach ($people as $p) : ?>
                            <option value="<?php echo esc_attr($p['name']); ?>"><?php echo esc_html($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="eos-form-field">
                <label><?php _e('Related Rock', 'eos-manager'); ?></label>
                <select id="issueRock">
                    <option value=""><?php _e('None', 'eos-manager'); ?></option>
                    <?php foreach ($rocks as $rock) : ?>
                        <option value="<?php echo intval($rock['id']); ?>"><?php echo esc_html($rock['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <div class="eos-modal-actions">
            <button class="eos-btn eos-btn-primary" onclick="saveIssue()"><?php _e('Save Issue', 'eos-manager'); ?></button>
            <button class="eos-btn" onclick="closeIssueModal()"><?php _e('Cancel', 'eos-manager'); ?></button>
        </div>
    </div>
</div>

<script>
function openIssueModal(row){
    if(row){
        jQuery('#issueModalTitle').text('<?php echo esc_js(__('Edit Issue', 'eos-manager')); ?>');
        jQuery('#issueId').val(row.data('id'));
        jQuery('#issueTitle').val(row.data('title'));
        jQuery('#issueDescription').val(row.data('description'));
        jQuery('#issuePriority').val(row.data('priority'));
        jQuery('#issueAssignee').val(row.data('assignee'));
        jQuery('#issueRock').val(row.data('rock'));
    } else {
        jQuery('#issueModalTitle').text('<?php echo esc_js(__('Add Issue', 'eos-manager')); ?>');
        jQuery('#issueForm')[0].reset();
        jQuery('#issueId').val('');
    }
    jQuery('#issueModal').show();
}
function closeIssueModal(){
    jQuery('#issueModal').hide();
}
function saveIssue(){
    var data={
        id:jQuery('#issueId').val(),
        title:jQuery('#issueTitle').val(),
        description:jQuery('#issueDescription').val(),
        priority:jQuery('#issuePriority').val(),
        assignee:jQuery('#issueAssignee').val(),
        related_rock_id:jQuery('#issueRock').val()
    };
    jQuery.post(ajaxurl,{action:'eos_save_issue',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',issue_data:data},function(res){
        if(res.success){location.reload();}else{alert(res.data?res.data.message:'Error');}
    });
}

jQuery('.issue-action.discuss').on('click',function(e){e.preventDefault();var id=jQuery(this).closest('tr').data('id');jQuery.post(ajaxurl,{action:'eos_update_issue_status',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',issue_id:id,status:'discussing'},function(){refreshIssueStats();});});

jQuery('.issue-action.solve').on('click',function(e){e.preventDefault();var row=jQuery(this).closest('tr');var solution=prompt('<?php echo esc_js(__('Enter solution', 'eos-manager')); ?>');if(solution){jQuery.post(ajaxurl,{action:'eos_solve_issue',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',issue_id:row.data('id'),solution:solution},function(){refreshIssueStats();location.reload();});}});

jQuery('.issue-action.assign').on('click',function(e){e.preventDefault();var row=jQuery(this).closest('tr');var assignee=prompt('<?php echo esc_js(__('Assign to (name)', 'eos-manager')); ?>',row.data('assignee'));if(assignee!==null){jQuery.post(ajaxurl,{action:'eos_assign_issue',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',issue_id:row.data('id'),assignee:assignee},function(){refreshIssueStats();location.reload();});}});

jQuery('.issue-action.delete').on('click',function(e){e.preventDefault();if(confirm('<?php echo esc_js(__('Delete this issue?', 'eos-manager')); ?>')){var id=jQuery(this).closest('tr').data('id');jQuery.post(ajaxurl,{action:'eos_delete_issue',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',issue_id:id},function(){refreshIssueStats();location.reload();});}});

jQuery('tr').on('dblclick',function(){openIssueModal(jQuery(this));});

function refreshIssueStats(){
    jQuery.get(ajaxurl,{action:'eos_get_issue_stats'},function(res){
        if(res.success){jQuery('#totalOpen').text(res.data.total_open);jQuery('#highPriority').text(res.data.high_priority);}
    });
}
</script>

<style>
.issue-status.identified{color:#555;}
.issue-status.discussing{color:#d9831f;}
.issue-status.solved{color:#46b450;}
.issue-status.closed{color:#777;}
.issue-priority.high{color:#dc3232;font-weight:bold;}
.issue-priority.medium{color:#ffb900;}
.issue-priority.low{color:#46b450;}
.eos-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;}
.eos-modal-content{background:#fff;padding:20px;max-width:600px;width:100%;}
.eos-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
</style>

