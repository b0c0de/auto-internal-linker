jQuery(document).ready(function ($) {
    $(".tracked-link").click(function (e) {
        e.preventDefault();

        let link = $(this);
        let trackUrl = link.attr("href");
        let finalUrl = link.data("final-url");

        $.get(trackUrl, function () {
            window.location.href = finalUrl;
        });
    });
});
