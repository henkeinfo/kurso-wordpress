// Auto-generate slug from name
jQuery(function ($) {
    $('#q_name').on('input', function () {
        var slug = $(this).val()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        var slugField = $('#q_slug');
        if (!slugField.prop('readonly')) {
            slugField.val(slug);
        }
    });
});
