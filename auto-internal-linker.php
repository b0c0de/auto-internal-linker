<?php
/**
 * Plugin Name: Auto-Internal Linker
 * Plugin URI: https://github.com/b0c0de
 * Description: A WordPress plugin that automatically links specific keywords to pre-defined internal URLs.
 * Version: 1.0.0
 * Author: Bojan Cvjetković
 * Author URI: https://brisk-web-services.com
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/class-linker.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Initialize the plugin
class AutoInternalLinker {
    private static $instance = null;

   public function settings_page_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_links';
    $links = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    ?>

    <div class="wrap">
        <h1>Auto-Internal Linker Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th>Keyword</th>
                    <th>URL</th>
                    <th>Enable</th>
                    <th>Remove</th>
                </tr>

                <tbody id="auto-linker-table">
                    <?php foreach ($links as $link) : ?>
                        <tr>
                            <td><input type="text" name="keywords[<?php echo esc_attr($link['id']); ?>][keyword]" value="<?php echo esc_attr($link['keyword']); ?>"></td>
                            <td><input type="text" name="keywords[<?php echo esc_attr($link['id']); ?>][url]" value="<?php echo esc_url($link['url']); ?>"></td>
                            <td><input type="checkbox" name="keywords[<?php echo esc_attr($link['id']); ?>][enabled]" value="1" <?php checked($link['enabled'], 1); ?>></td>
                            <td><button type="button" class="remove-keyword button">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="button" id="add-keyword" class="button">Add Keyword</button>
            <br><br>

            <?php submit_button('Save Keywords', 'primary', 'save_keywords'); ?>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('add-keyword').addEventListener('click', function () {
                let table = document.getElementById('auto-linker-table');
                let row = document.createElement('tr');

                row.innerHTML = `
                    <td><input type="text" name="new_keyword[]" value=""></td>
                    <td><input type="text" name="new_url[]" value=""></td>
                    <td><input type="checkbox" name="new_enabled[]" value="1" checked></td>
                    <td><button type="button" class="remove-keyword button">Remove</button></td>
                `;

                table.appendChild(row);
            });

            document.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-keyword')) {
                    event.target.closest('tr').remove();
                }
            });
        });
    </script>

    <?php
}



    private function __construct() {
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('the_content', ['AutoInternalLinker_Linker', 'apply_internal_links']);
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_settings_page() {
        add_options_page('Auto Internal Linker', 'Auto Linker', 'manage_options', 'auto-internal-linker', ['AutoInternalLinker_Settings', 'settings_page_html']);
    }

   public function register_settings() {
    register_setting('auto_internal_linker_group', 'auto_internal_links');
    register_setting('auto_internal_linker_group', 'auto_internal_excluded_pages');
    register_setting('auto_internal_linker_group', 'auto_internal_excluded_post_types');
}


    public function enqueue_admin_assets() {
    wp_enqueue_style('auto-internal-linker-admin', plugin_dir_url(__FILE__) . 'assets/admin-style.css');
    wp_enqueue_script('auto-internal-linker-admin', plugin_dir_url(__FILE__) . 'assets/admin-script.js', ['jquery'], null, true);
}
add_action('admin_enqueue_scripts', ['AutoInternalLinker', 'enqueue_admin_assets']);

}

public function save_keywords() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_links';

    // Update existing keywords
    if (!empty($_POST['keywords'])) {
        foreach ($_POST['keywords'] as $id => $data) {
            $wpdb->update(
                $table_name,
                [
                    'keyword' => sanitize_text_field($data['keyword']),
                    'url' => esc_url_raw($data['url']),
                    'enabled' => isset($data['enabled']) ? 1 : 0
                ],
                ['id' => (int) $id]
            );
        }
    }

    // Insert new keywords
    if (!empty($_POST['new_keyword'])) {
        for ($i = 0; $i < count($_POST['new_keyword']); $i++) {
            $wpdb->insert(
                $table_name,
                [
                    'keyword' => sanitize_text_field($_POST['new_keyword'][$i]),
                    'url' => esc_url_raw($_POST['new_url'][$i]),
                    'enabled' => isset($_POST['new_enabled'][$i]) ? 1 : 0
                ]
            );
        }
    }
}
add_action('admin_post_save_keywords', ['AutoInternalLinker', 'save_keywords']);


// Function to create custom database table
function auto_internal_linker_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_links';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword varchar(255) NOT NULL,
        url text NOT NULL,
        enabled tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        UNIQUE KEY keyword (keyword)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

public function apply_internal_links($content) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_links';

    $links = $wpdb->get_results("SELECT * FROM $table_name WHERE enabled = 1", ARRAY_A);

    if (!empty($links)) {
        foreach ($links as $link) {
            $content = preg_replace("/\b" . preg_quote($link['keyword'], '/') . "\b/i", '<a href="' . esc_url($link['url']) . '">' . esc_html($link['keyword']) . '</a>', $content, 1);
        }
    }

    return $content;
}

// Add meta box to post editor
function auto_internal_linker_add_meta_box() {
    add_meta_box(
        'auto_internal_linker_meta_box',
        'Auto Internal Linker',
        'auto_internal_linker_meta_box_callback',
        ['post', 'page'], // Add to posts and pages
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'auto_internal_linker_add_meta_box');

// Callback function to render meta box
function auto_internal_linker_meta_box_callback($post) {
    $value = get_post_meta($post->ID, '_disable_auto_internal_links', true);
    wp_nonce_field('auto_internal_linker_meta_box', 'auto_internal_linker_meta_box_nonce');

    ?>
    <p>
        <label for="disable_auto_internal_links">
            <input type="checkbox" name="disable_auto_internal_links" id="disable_auto_internal_links" value="1" <?php checked($value, '1'); ?>>
            Disable auto internal linking for this post
        </label>
    </p>
    <?php
}



// Hook to create table on plugin activation
register_activation_hook(__FILE__, 'auto_internal_linker_create_table');


// Start the plugin
AutoInternalLinker::get_instance();
