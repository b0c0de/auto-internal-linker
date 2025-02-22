jQuery(document).ready(function ($) {
    // Add Keyword
    $("#add-keyword").click(function () {
        var keyword = $("#new-keyword").val();
        var url = $("#new-url").val();

        if (keyword && url) {
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "add_internal_link",
                    keyword: keyword,
                    url: url,
                },
                success: function (response) {
                    location.reload();
                }
            });
        } else {
            $("#message-box").html('<div class="error">Please enter both keyword and URL.</div>');
        }
    });

    // Remove Keyword
    $(".remove-keyword").click(function () {
        var keyword = $(this).data("keyword");

        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: { action: "remove_internal_link", keyword: keyword },
            success: function () {
                location.reload();
            }
        });
    });
});
