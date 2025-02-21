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
    $links = get_option('auto_internal_links', []);

    ?>
    <div class="wrap">
        <h1>Auto-Internal Linker Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auto_internal_linker_group');
            do_settings_sections('auto_internal_linker_group');
            ?>
            
            <table class="form-table">
                <tr>
                    <th>Keyword</th>
                    <th>URL</th>
                    <th>Enable</th>
                    <th>Remove</th>
                </tr>

                <tbody id="auto-linker-table">
                    <?php if (!empty($links)) : ?>
                        <?php foreach ($links as $keyword => $data) : ?>
                            <tr>
                                <td><input type="text" name="auto_internal_links[<?php echo esc_attr($keyword); ?>][keyword]" value="<?php echo esc_attr($keyword); ?>"></td>
                                <td><input type="text" name="auto_internal_links[<?php echo esc_attr($keyword); ?>][url]" value="<?php echo esc_url($data['url']); ?>"></td>
                                <td><input type="checkbox" name="auto_internal_links[<?php echo esc_attr($keyword); ?>][enabled]" value="1" <?php checked($data['enabled'], 1); ?>></td>
                                <td><button type="button" class="remove-keyword button">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" id="add-keyword" class="button">Add Keyword</button>
            <br><br>
            
            <?php submit_button(); ?>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('add-keyword').addEventListener('click', function () {
                let table = document.getElementById('auto-linker-table');
                let row = document.createElement('tr');

                row.innerHTML = `
                    <td><input type="text" name="auto_internal_links[new][keyword]" value=""></td>
                    <td><input type="text" name="auto_internal_links[new][url]" value=""></td>
                    <td><input type="checkbox" name="auto_internal_links[new][enabled]" value="1"></td>
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

// Start the plugin
AutoInternalLinker::get_instance();
