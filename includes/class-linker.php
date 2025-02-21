<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Linker {
    public function apply_internal_links($content) {
    $links = get_option('auto_internal_links', '{}');
    $links = json_decode($links, true);

    if (!empty($links)) {
        foreach ($links as $keyword => $data) {
            $url = esc_url($data['url']);
            $limit = isset($data['limit']) ? intval($data['limit']) : 1;

            $pattern = "/\b" . preg_quote($keyword, '/') . "\b/i";
            $count = 0;

            $content = preg_replace_callback($pattern, function ($matches) use ($url, $keyword, &$count, $limit) {
                if ($count < $limit) {
                    $count++;
                    return '<a href="' . $url . '">' . esc_html($keyword) . '</a>';
                }
                return $matches[0];
            }, $content);
        }
    }

    return $content;
}
