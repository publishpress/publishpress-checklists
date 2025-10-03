jQuery(function ($) {
    $('#pp-checklists-global select').select2({
        language: {
          noResults: function () {
            return objectL10n_checklists_global_checklist.noResults;
          },
          searching: function () {
            return objectL10n_checklists_global_checklist.searching;
          }
        },
      });
    // Re-initialize select 2 with ajax
    function initializeSelect2(selector, identifier) {
        $(selector).select2({
            ajax: {
                url: objectL10n_checklists_global_checklist.ajaxurl,
                dataType: 'json',
                delay: 250,
                type: 'POST',
                cache: true,
                data: function (params) {
                    return {
                        action: 'pp_checklists_' + identifier,
                        nonce: objectL10n_checklists_global_checklist.nonce,
                        q: params.term,
                        page: params.page || 1,
                    };
                },
                processResults: function (res, params) {
                    params.page = params.page || 1;
                    return {
                        results: res.data.items,
                        pagination: { more: res.data.has_next },
                    };
                },
            },
            language: {
                noResults: function () {
                    return objectL10n_checklists_global_checklist.noResults;
                },
                searching: function () {
                    return objectL10n_checklists_global_checklist.searching;
                }
            },
        });
    }
    initializeSelect2('#post-checklists-required_categories_multiple', 'required_category');
    initializeSelect2('#post-checklists-prohibited_categories_multiple', 'prohibited_category');
    initializeSelect2('#post-checklists-required_tags_multiple', 'required_tag');
    initializeSelect2('#post-checklists-prohibited_tags_multiple', 'prohibited_tag');
});
