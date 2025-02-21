<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Linker {
 public function apply_internal_links($content) {
    if ($this->should_exclude_page()) {
        return $content;
    }

    $cache_key = 'auto_internal_links_' . md5($content);
    $cached_content = get_transient($cache_key);

    if ($cached_content !== false) {
        return $cached_content;
    }

    $content = $this->optimized_linking($content);

    // Cache result for 12 hours
    set_transient($cache_key, $content, 12 * HOUR_IN_SECONDS);
    return $content;
}

/**
 * Check if the current post/page should be excluded from internal linking.
 */
private function should_exclude_page() {
    if (is_admin()) {
        return true; // Don't apply links in the admin panel
    }

    $excluded_pages = get_option('auto_internal_excluded_pages', '');
    $excluded_post_types = get_option('auto_internal_excluded_post_types', '');

    $excluded_pages = !empty($excluded_pages) ? explode(',', $excluded_pages) : [];
    $excluded_post_types = !empty($excluded_post_types) ? explode(',', $excluded_post_types) : [];

    global $post;
    if (!$post) {
        return false;
    }

    $post_id = $post->ID;
    $post_type = get_post_type($post);

    // Check if post ID is excluded
    if (in_array($post_id, $excluded_pages)) {
        return true;
    }

    // Check if post type is excluded
    if (in_array($post_type, $excluded_post_types)) {
        return true;
    }

    return false;
}

private function optimized_linking($content) {
    $links = get_option('auto_internal_links', '{}');
    $links = json_decode($links, true);

    if (empty($links) || strlen($content) < 100) { 
        return $content;
    }

    $words = explode(' ', $content);
    $word_limit = 1000;
    $processed_words = array_slice($words, 0, $word_limit);
    $remaining_words = array_slice($words, $word_limit);

    foreach ($links as $keyword => $data) {
        $url = esc_url($data['url']);
        $limit = isset($data['limit']) ? intval($data['limit']) : 1;

        $count = 0;
        foreach ($processed_words as &$word) {
            if (stripos($word, $keyword) !== false && $count < $limit) {
                $word = '<a href="' . $url . '">' . esc_html($keyword) . '</a>';
                $count++;
            }
        }
    }

    return implode(' ', $processed_words) . ' ' . implode(' ', $remaining_words);
}


