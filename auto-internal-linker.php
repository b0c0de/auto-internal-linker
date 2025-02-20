<?php
/**
 * Plugin Name: Auto-Internal Linker
 * Plugin URI: https://github.com/yourusername/auto-internal-linker
 * Description: A plugin that automatically adds internal links based on predefined keywords.
 * Version: 1.0.0
 * Author: Bojan Cvjetković
 * Author URI: https://brisk-web-services.com
 * License: GPL v2 or later
 * Text Domain: auto-internal-linker
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/class-linker.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';

// Initialize plugin
function ail_init() {
    new AutoInternalLinker();
    new AutoInternalLinkerSettings();
}
add_action('plugins_loaded', 'ail_init');
