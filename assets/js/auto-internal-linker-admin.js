jQuery(document).ready(function ($) {
    $("#load-keywords").click(function () {
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: { action: "load_internal_links" },
            success: function (response) {
                $("#keywords-list").html(response);
            }
        });
    });
});
