<?php
/**
 * Plugin Name: Auto-Internal Linker
 * Plugin URI: https://github.com/your-repo-link
 * Description: A WordPress plugin that automatically links specific keywords to pre-defined internal URLs.
 * Version: 1.3.0
 * Author: Bojan Cvjetković
 * Author URI: https://brisk-web-services.com
 * License: GPL2
 * Network: true  // Enables multisite support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker {
    private static $instance = null;
    
    private function __construct() {
        add_action('network_admin_menu', [$this, 'create_network_settings_page']);
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('the_content', [$this, 'apply_internal_links']);
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_options_page('Auto Internal Linker', 'Auto Linker', 'manage_options', 'auto-internal-linker', [$this, 'settings_page_html']);
    }

    public function create_network_settings_page() {
        if (!is_multisite() || !current_user_can('manage_network_options')) {
            return;
        }
        add_submenu_page('settings.php', 'Auto Linker Network Settings', 'Auto Linker (Network)', 'manage_network_options', 'auto-internal-linker-network', [$this, 'network_settings_page_html']);
    }

    public function register_settings() {
        register_setting('auto_internal_linker_group', 'auto_internal_links');
        if (is_multisite()) {
            register_setting('auto_internal_linker_network_group', 'auto_internal_links_network');
        }
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
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

    public function network_settings_page_html() {
        if (!current_user_can('manage_network_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Auto-Internal Linker Network Settings</h1>
            <form method="post" action="edit.php?action=update_network_option">
                <?php
                settings_fields('auto_internal_linker_network_group');
                do_settings_sections('auto_internal_linker_network_group');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function apply_internal_links($content) {
        $links = is_multisite() ? get_site_option('auto_internal_links_network', []) : get_option('auto_internal_links', []);
        if (!empty($links)) {
            foreach ($links as $keyword => $url) {
                $content = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/i", '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content, 1);
            }
        }
        return $content;
    }
}

AutoInternalLinker::get_instance();
