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
        $this->log_file = WP_CONTENT_DIR . '/debug-auto-linker.log';
        add_action('admin_notices', [$this, 'display_admin_notices']);
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
    register_setting('auto_internal_linker_group', 'auto_internal_links', [
        'sanitize_callback' => [$this, 'sanitize_links']
    ]);
}

public function sanitize_links($input) {
    $sanitized_links = [];
    if (is_array($input)) {
        foreach ($input as $keyword => $url) {
            $sanitized_keyword = sanitize_text_field($keyword);
            $sanitized_url = esc_url_raw($url);
            $sanitized_links[$sanitized_keyword] = $sanitized_url;
        }
    }
    return $sanitized_links;
}

    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
      <div class="wrap">
        <h1><?php esc_html_e('Auto-Internal Linker Settings', 'auto-internal-linker'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auto_internal_linker_group');
            do_settings_sections('auto_internal_linker_group');
            wp_nonce_field('auto_internal_linker_save', 'auto_internal_linker_nonce'); // 🔒 Add nonce
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
    if (is_admin()) {
        return $content; // Don't modify content in the admin area
    }

    $links = get_option('auto_internal_links', []);

    if ($links === false) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Auto-Internal Linker Error: Failed to retrieve link settings.');
        }
        return $content;
    }

    if (!empty($links)) {
        foreach ($links as $keyword => $url) {
            if (empty($keyword) || empty($url)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Auto-Internal Linker Warning: Empty keyword or URL for {$keyword}");
                }
                continue;
            }

            if (stripos($content, $keyword) !== false) {
                $content = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/i", '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content, 1);
            }
        }
    }

    return $content;
}


     private function log_error($message) {
        if (WP_DEBUG) {
            error_log("[Auto-Internal Linker] " . $message);
        }
        file_put_contents($this->log_file, date("Y-m-d H:i:s") . " - " . $message . "\n", FILE_APPEND);
        set_transient('ail_admin_error', $message, 10);
    }

      public function display_admin_notices() {
        if ($error = get_transient('ail_admin_error')) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            delete_transient('ail_admin_error');
        }
    }

    public function admin_notices() {
    if (!get_option('auto_internal_links')) {
        echo '<div class="notice notice-warning is-dismissible">
                <p><strong>Auto-Internal Linker:</strong> No keywords have been set. Please configure your keyword links in <a href="options-general.php?page=auto-internal-linker">plugin settings</a>.</p>
              </div>';
    }
}
add_action('admin_notices', [$this, 'admin_notices']);

register_activation_hook(__FILE__, 'auto_internal_linker_create_log_table');

function auto_internal_linker_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_logs';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        action TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
public function log_change($action) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_logs';
    $user_id = get_current_user_id();

    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'action'  => sanitize_text_field($action),
    ]);
}

public function sanitize_links($input) {
    $sanitized_links = [];

    if (is_array($input)) {
        foreach ($input as $keyword => $url) {
            $sanitized_keyword = sanitize_text_field($keyword);
            $sanitized_url = esc_url_raw($url);
            $sanitized_links[$sanitized_keyword] = $sanitized_url;
        }
    }

    $this->log_change('Updated Auto-Internal Linker settings');

    return $sanitized_links;
}


}

AutoInternalLinker::get_instance();
