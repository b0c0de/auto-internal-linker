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
function auto_internal_linker_log_debug($message) {
    if (!get_option('auto_internal_linker_debug_mode', 0)) {
        return; // Exit if debug mode is off
    }

    $log_file = WP_CONTENT_DIR . '/debug.log';
    $timestamp = date("Y-m-d H:i:s");

    $log_entry = "[$timestamp] $message" . PHP_EOL;

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function auto_internal_linker_log_slow_queries($query, $execution_time) {
    if ($execution_time > 0.5) { // Log only slow queries (over 500ms)
        auto_internal_linker_log_debug("⚠ SLOW QUERY: {$query} took {$execution_time} sec.");
    }
}

add_filter('query', function($query) {
    if (get_option('auto_internal_linker_debug_mode', 0)) {
        $start = microtime(true);
        $result = $query;
        $execution_time = round(microtime(true) - $start, 4);
        auto_internal_linker_log_slow_queries($query, $execution_time);
        return $result;
    }
    return $query;
});
