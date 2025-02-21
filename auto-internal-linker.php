<?php
/**
 * Plugin Name: Auto-Internal Linker
 * Plugin URI: https://github.com/your-repo-link
 * Description: A WordPress plugin that automatically links specific keywords to pre-defined internal URLs.
 * Version: 1.0.0
 * Author: Bojan Cvjetković
 * Author URI: https://brisk-web-services.com
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker {
    private static $instance = null;
    private $cached_links = [];
    private $cache_key = 'auto_internal_link_cache';
    private $cache_expiration = HOUR_IN_SECONDS; // 1-hour cache expiration

    private function __construct() {
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('the_content', [$this, 'apply_internal_links']);
        add_action('wp_ajax_auto_internal_link_click', [$this, 'track_link_click']);
        add_action('wp_ajax_nopriv_auto_internal_link_click', [$this, 'track_link_click']);
        add_action('auto_internal_link_cron', [$this, 'process_click_stats']);
        if (!wp_next_scheduled('auto_internal_link_cron')) {
            wp_schedule_event(time(), 'hourly', 'auto_internal_link_cron');
        }
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_settings_page() {
        add_options_page('Auto Internal Linker', 'Auto Linker', 'manage_options', 'auto-internal-linker', [$this, 'settings_page_html']);
    }

    public function register_settings() {
        register_setting('auto_internal_linker_group', 'auto_internal_links');
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Auto-Internal Linker Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('auto_internal_linker_group');
                do_settings_sections('auto_internal_linker_group');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function get_cached_links() {
        $links = get_transient($this->cache_key);
        if ($links === false) {
            $links = get_option('auto_internal_links', []);
            set_transient($this->cache_key, $links, $this->cache_expiration);
        }
        return $links;
    }

    public function apply_internal_links($content) {
        $links = $this->get_cached_links();
        if (!empty($links)) {
            foreach ($links as $keyword => $url) {
                $content = preg_replace("/\\b" . preg_quote($keyword, '/') . "\\b/i", '<a href="' . esc_url($url) . '" class="auto-internal-link" data-keyword="' . esc_attr($keyword) . '">' . esc_html($keyword) . '</a>', $content, 1);
            }
        }
        return $content;
    }

    public function track_link_click() {
        if (isset($_POST['keyword'])) {
            $keyword = sanitize_text_field($_POST['keyword']);
            $clicks = get_option('auto_internal_link_clicks', []);
            if (!isset($clicks[$keyword])) {
                $clicks[$keyword] = 0;
            }
            $clicks[$keyword]++;
            update_option('auto_internal_link_clicks', $clicks);
        }
        wp_die();
    }

    public function process_click_stats() {
        $clicks = get_option('auto_internal_link_clicks', []);
        if (!empty($clicks)) {
            update_option('auto_internal_link_clicks', $clicks);
        }
    }
}

AutoInternalLinker::get_instance();

// JavaScript for click tracking
enqueue_script('auto-internal-link-tracker', plugin_dir_url(__FILE__) . 'tracker.js', ['jquery'], null, true);
?>
