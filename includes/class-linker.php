<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Linker {
    public static function apply_internal_links($content) {
        $json_links = get_option('auto_internal_links', '');
        
        if (empty($json_links)) {
            return $content;
        }

        $links = json_decode($json_links, true);
        if (!is_array($links)) {
            return $content;
        }

        foreach ($links as $keyword => $url) {
            $content = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/i", '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content, 1);
        }
        
        return $content;
    }
}
