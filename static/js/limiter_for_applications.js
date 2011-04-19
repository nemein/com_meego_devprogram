jQuery(document).ready(function() {
    jQuery('textarea[name="summary"]').after('<span class="summarycounter"></span>');
    jQuery('textarea[name="summary"]').textlimit('span.summarycounter', 200);

    jQuery('textarea[name="plan"]').after('<span class="plancounter"></span>');
    jQuery('textarea[name="plan"]').textlimit('span.plancounter', 500);
});
