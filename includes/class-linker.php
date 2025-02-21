<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Linker {
 public function apply_internal_links($content) {
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


