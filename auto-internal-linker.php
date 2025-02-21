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
    ?>
    <div class="wrap">
        <h1>Auto-Internal Linker Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auto_internal_linker_group');
            do_settings_sections('auto_internal_linker_group');
            submit_button();
            ?>

            <h2>Exclude from Linking</h2>
            <label for="auto_internal_excluded_pages">Exclude Pages (comma-separated post IDs):</label><br>
            <input type="text" id="auto_internal_excluded_pages" name="auto_internal_excluded_pages" value="<?php echo esc_attr(get_option('auto_internal_excluded_pages', '')); ?>" style="width: 100%;"><br><br>

            <label for="auto_internal_excluded_post_types">Exclude Post Types (comma-separated):</label><br>
            <input type="text" id="auto_internal_excluded_post_types" name="auto_internal_excluded_post_types" value="<?php echo esc_attr(get_option('auto_internal_excluded_post_types', '')); ?>" style="width: 100%;"><br><br>

            <?php submit_button(); ?>
        </form>
    </div>
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
