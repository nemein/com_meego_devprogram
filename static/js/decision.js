jQuery(document).ready(function() {
    var decisionOptions = jQuery('input[name=status]');

    jQuery.each(decisionOptions, function(index, decisionOption) {
        var decisionOption = jQuery(decisionOption);

        decisionOption.change(function(e) {
            jQuery('input[type=submit]').attr('disabled', '');
        });
    });
});
