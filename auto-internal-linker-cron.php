function auto_internal_linker_schedule_cron() {
    if (!wp_next_scheduled('auto_internal_linker_update_cache')) {
        wp_schedule_event(time(), 'hourly', 'auto_internal_linker_update_cache');
    }
}
add_action('wp', 'auto_internal_linker_schedule_cron');

function auto_internal_linker_update_cache() {
    global $wpdb;

    $posts = get_posts([
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'post_type'      => 'post',
    ]);

    foreach ($posts as $post) {
        $cache_key = 'auto_internal_links_cache_' . md5($post->post_content);
        set_transient($cache_key, auto_internal_linker_apply_links($post->post_content), 24 * HOUR_IN_SECONDS);
    }
}
add_action('auto_internal_linker_update_cache', 'auto_internal_linker_update_cache');
