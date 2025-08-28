<?php
if (!defined('ABSPATH')) {
    exit;
}

$sections = EOS_VTO::get_all_sections();
?>
<div class="wrap">
    <h1><?php esc_html_e('Vision/Traction Organizer', 'eos-manager'); ?></h1>
    <div class="eos-vto-accordion">
        <?php foreach ($sections as $key => $label) :
            $content = EOS_VTO::get_section($key);
            ?>
            <h2 class="eos-vto-toggle" onclick="toggleVTO('<?php echo esc_attr($key); ?>')"><?php echo esc_html($label); ?></h2>
            <div id="section-<?php echo esc_attr($key); ?>" class="eos-vto-section" style="display:none;">
                <p class="description"><?php printf(__('Fill out the %s section following EOS guidance.', 'eos-manager'), esc_html($label)); ?></p>
                <textarea id="content-<?php echo esc_attr($key); ?>" rows="6" style="width:100%;"><?php echo esc_textarea($content); ?></textarea>
                <p><button class="button button-primary" onclick="saveVTOSection('<?php echo esc_js($key); ?>')"><?php _e('Save Section', 'eos-manager'); ?></button></p>
                <p class="eos-vto-message" id="message-<?php echo esc_attr($key); ?>"></p>
            </div>
        <?php endforeach; ?>
    </div>
    <p><small><?php printf(__('Last updated: %s', 'eos-manager'), esc_html(EOS_VTO::get_last_updated())); ?></small></p>
</div>

<script>
function toggleVTO(key){
    var el=document.getElementById('section-'+key);
    if(el.style.display==='none'){el.style.display='block';}else{el.style.display='none';}
}
function saveVTOSection(key){
    var content=jQuery('#content-'+key).val();
    if(content.trim()===''){
        jQuery('#message-'+key).text('<?php echo esc_js(__('Content cannot be empty.', 'eos-manager')); ?>').css('color','red');
        return;
    }
    jQuery.post(ajaxurl,{action:'eos_save_vto_section',nonce:'<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',section:key,content:content},function(res){
        if(res.success){jQuery('#message-'+key).text(res.data.message).css('color','green');}else{jQuery('#message-'+key).text(res.data.message).css('color','red');}
    });
}
</script>

<style>
.eos-vto-toggle{cursor:pointer;margin-top:20px;background:#f1f1f1;padding:10px;}
.eos-vto-section{border:1px solid #ddd;padding:10px;margin-bottom:10px;}
</style>

