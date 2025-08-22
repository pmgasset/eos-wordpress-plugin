<?php
if (!defined('ABSPATH')) {
    exit;
}

$status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'active';
$department = isset($_GET['department']) ? sanitize_text_field($_GET['department']) : '';

$args = array();
if ($status === 'active') {
    $args['is_active'] = true;
} elseif ($status === 'inactive') {
    $args['is_active'] = false;
} else {
    $args['is_active'] = null;
}
if (!empty($department)) {
    $args['department'] = $department;
}

$people = EOS_People::get_people($args);
$departments = EOS_People::get_departments();
?>
<div class="wrap">
    <h1><?php esc_html_e('People Analyzer', 'eos-manager'); ?></h1>
    <form method="get" class="eos-people-filters">
        <input type="hidden" name="page" value="eos-people" />
        <select name="status">
            <option value="all" <?php selected($status, 'all'); ?>><?php _e('All', 'eos-manager'); ?></option>
            <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'eos-manager'); ?></option>
            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Inactive', 'eos-manager'); ?></option>
        </select>
        <select name="department">
            <option value=""><?php _e('All Departments', 'eos-manager'); ?></option>
            <?php foreach ($departments as $dept) : ?>
                <option value="<?php echo esc_attr($dept); ?>" <?php selected($department, $dept); ?>><?php echo esc_html($dept); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button"><?php _e('Filter', 'eos-manager'); ?></button>
        <button type="button" class="button button-primary" onclick="openPersonModal();"><?php _e('Add Person', 'eos-manager'); ?></button>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Name', 'eos-manager'); ?></th>
                <th><?php _e('Seat', 'eos-manager'); ?></th>
                <th><?php _e('Department', 'eos-manager'); ?></th>
                <th><?php _e('G', 'eos-manager'); ?></th>
                <th><?php _e('W', 'eos-manager'); ?></th>
                <th><?php _e('C', 'eos-manager'); ?></th>
                <th><?php _e('Status', 'eos-manager'); ?></th>
                <th><?php _e('Actions', 'eos-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($people)) : ?>
                <?php foreach ($people as $person) : ?>
                    <tr data-id="<?php echo intval($person['id']); ?>"
                        data-name="<?php echo esc_attr($person['name']); ?>"
                        data-role="<?php echo esc_attr($person['role']); ?>"
                        data-seat="<?php echo esc_attr($person['seat']); ?>"
                        data-department="<?php echo esc_attr($person['department']); ?>"
                        data-email="<?php echo esc_attr($person['email']); ?>"
                        data-notes="<?php echo esc_attr($person['notes']); ?>"
                        data-getit="<?php echo intval($person['get_it']); ?>"
                        data-wantit="<?php echo intval($person['want_it']); ?>"
                        data-capacity="<?php echo intval($person['capacity']); ?>"
                        data-active="<?php echo intval($person['is_active']); ?>">
                        <td><?php echo esc_html($person['name']); ?></td>
                        <td><?php echo esc_html($person['seat']); ?></td>
                        <td><?php echo esc_html($person['department']); ?></td>
                        <td><input type="checkbox" class="gwc" data-field="get_it" <?php checked($person['get_it']); ?>></td>
                        <td><input type="checkbox" class="gwc" data-field="want_it" <?php checked($person['want_it']); ?>></td>
                        <td><input type="checkbox" class="gwc" data-field="capacity" <?php checked($person['capacity']); ?>></td>
                        <td><?php echo esc_html(ucfirst($person['status'])); ?></td>
                        <td>
                            <a href="#" class="edit-person"><?php _e('Edit', 'eos-manager'); ?></a> |
                            <a href="#" class="delete-person" style="color:red;">&times;</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="8"><?php _e('No people found.', 'eos-manager'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="personModal" class="eos-modal">
    <div class="eos-modal-content">
        <div class="eos-modal-header">
            <h2 id="personModalTitle"><?php _e('Add Person', 'eos-manager'); ?></h2>
            <button class="eos-modal-close" onclick="closePersonModal()">&times;</button>
        </div>
        <form id="personForm">
            <input type="hidden" id="personId" />
            <div class="eos-form-field">
                <label><?php _e('Name', 'eos-manager'); ?></label>
                <input type="text" id="personName" required />
            </div>
            <div class="eos-form-field">
                <label><?php _e('Role', 'eos-manager'); ?></label>
                <input type="text" id="personRole" />
            </div>
            <div class="eos-form-field">
                <label><?php _e('Seat', 'eos-manager'); ?></label>
                <input type="text" id="personSeat" />
            </div>
            <div class="eos-form-field">
                <label><?php _e('Department', 'eos-manager'); ?></label>
                <input type="text" id="personDepartment" />
            </div>
            <div class="eos-form-field">
                <label><?php _e('Email', 'eos-manager'); ?></label>
                <input type="email" id="personEmail" />
            </div>
            <div class="eos-form-row">
                <label><?php _e('GWC', 'eos-manager'); ?></label>
                <input type="checkbox" id="personGet" /> <?php _e('Get it', 'eos-manager'); ?>
                <input type="checkbox" id="personWant" /> <?php _e('Want it', 'eos-manager'); ?>
                <input type="checkbox" id="personCap" /> <?php _e('Capacity', 'eos-manager'); ?>
            </div>
            <div class="eos-form-field">
                <label><?php _e('Notes', 'eos-manager'); ?></label>
                <textarea id="personNotes"></textarea>
            </div>
            <div class="eos-form-field">
                <label><input type="checkbox" id="personActive" checked> <?php _e('Active', 'eos-manager'); ?></label>
            </div>
        </form>
        <div class="eos-modal-actions">
            <button class="eos-btn eos-btn-primary" onclick="savePerson()"><?php _e('Save Person', 'eos-manager'); ?></button>
            <button class="eos-btn" onclick="closePersonModal()"><?php _e('Cancel', 'eos-manager'); ?></button>
        </div>
    </div>
</div>

<script>
function openPersonModal(row){
    if(row){
        jQuery('#personModalTitle').text('<?php echo esc_js(__('Edit Person', 'eos-manager')); ?>');
        jQuery('#personId').val(row.data('id'));
        jQuery('#personName').val(row.data('name'));
        jQuery('#personRole').val(row.data('role'));
        jQuery('#personSeat').val(row.data('seat'));
        jQuery('#personDepartment').val(row.data('department'));
        jQuery('#personEmail').val(row.data('email'));
        jQuery('#personNotes').val(row.data('notes'));
        jQuery('#personGet').prop('checked', row.data('getit') == 1);
        jQuery('#personWant').prop('checked', row.data('wantit') == 1);
        jQuery('#personCap').prop('checked', row.data('capacity') == 1);
        jQuery('#personActive').prop('checked', row.data('active') == 1);
    } else {
        jQuery('#personModalTitle').text('<?php echo esc_js(__('Add Person', 'eos-manager')); ?>');
        jQuery('#personForm')[0].reset();
        jQuery('#personId').val('');
        jQuery('#personActive').prop('checked', true);
    }
    jQuery('#personModal').show();
}
function closePersonModal(){
    jQuery('#personModal').hide();
}
function savePerson(){
    var data={
        id:jQuery('#personId').val(),
        name:jQuery('#personName').val(),
        role:jQuery('#personRole').val(),
        seat:jQuery('#personSeat').val(),
        department:jQuery('#personDepartment').val(),
        email:jQuery('#personEmail').val(),
        get_it:jQuery('#personGet').is(':checked') ? 1 : 0,
        want_it:jQuery('#personWant').is(':checked') ? 1 : 0,
        capacity:jQuery('#personCap').is(':checked') ? 1 : 0,
        notes:jQuery('#personNotes').val(),
        is_active:jQuery('#personActive').is(':checked') ? 1 : 0
    };
    jQuery.post(ajaxurl,{action:'eos_save_person',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',person_data:data},function(res){
        if(res.success){location.reload();}else{alert(res.data?res.data.message:'Error');}
    });
}

jQuery('.gwc').on('change',function(){
    var row=jQuery(this).closest('tr');
    var field=jQuery(this).data('field');
    var get=row.find('input[data-field="get_it"]').is(':checked')?1:0;
    var want=row.find('input[data-field="want_it"]').is(':checked')?1:0;
    var cap=row.find('input[data-field="capacity"]').is(':checked')?1:0;
    jQuery.post(ajaxurl,{action:'eos_update_gwc',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',person_id:row.data('id'),get_it:get,want_it:want,capacity:cap});
});

jQuery('.edit-person').on('click',function(e){e.preventDefault();openPersonModal(jQuery(this).closest('tr'));});
jQuery('.delete-person').on('click',function(e){e.preventDefault();if(confirm('<?php echo esc_js(__('Delete this person?', 'eos-manager')); ?>')){var id=jQuery(this).closest('tr').data('id');jQuery.post(ajaxurl,{action:'eos_delete_person',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',person_id:id},function(){location.reload();});}});
</script>

<style>
.eos-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;}
.eos-modal-content{background:#fff;padding:20px;max-width:600px;width:100%;}
.eos-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
</style>

