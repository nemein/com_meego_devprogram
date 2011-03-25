jQuery(document).ready(function() {
    var calendarEntries = jQuery('input[type=date]');
    jQuery.each(calendarEntries, function(index, calendarEntry) {
        var calendarEntry = jQuery(calendarEntry);
        if (calendarEntry.attr('readonly')) {
            return true;
        }
        calendarEntry.datepicker({
            dateFormat: 'yy-mm-dd'
        });
    });
});
