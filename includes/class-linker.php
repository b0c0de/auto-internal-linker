<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Linker {
   public function apply_internal_links($content) {
    $links = get_option('auto_internal_links', '{}');
    $links = json_decode($links, true);

    if (empty($links) || strlen($content) < 100) { // Skip processing for short content
        return $content;
    }

    // Process only the first 1000 words for efficiency
    $words = explode(' ', $content);
    $word_limit = 1000;
    $processed_words = array_slice($words, 0, $word_limit);
    $remaining_words = array_slice($words, $word_limit);

    foreach ($links as $keyword => $data) {
        $url = esc_url($data['url']);
        $limit = isset($data['limit']) ? intval($data['limit']) : 1;

        // Track how many times we have linked this keyword
        $count = 0;
        foreach ($processed_words as &$word) {
            if (stripos($word, $keyword) !== false && $count < $limit) {
                $word = '<a href="' . $url . '">' . esc_html($keyword) . '</a>';
                $count++;
            }
        }
    }

    // Reassemble content after optimization
    return implode(' ', $processed_words) . ' ' . implode(' ', $remaining_words);
}

