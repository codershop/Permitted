jQuery(document).ready(function() {
    jQuery('#permitted_instruction').click(function() {
        if (jQuery('#permitted_instructions').css('display') == 'none') {
            jQuery('#permitted_instructions').show();
            jQuery(this).text('Hide Instructions');
        } else {
            jQuery('#permitted_instructions').hide();
            jQuery(this).text('Show Instructions');
        }
    }).trigger('click');
});