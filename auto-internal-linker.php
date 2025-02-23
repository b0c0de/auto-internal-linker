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

    add_options_page(
        'Auto Internal Linker', 
        'Auto Linker', 
        'manage_options', 
        'auto-internal-linker', 
        [$this, 'settings_page_html']
    );

    add_submenu_page(
        'options-general.php', 
        'Debug Log', 
        'Debug Log', 
        'manage_options', 
        'auto-internal-linker-debug', 
        [$this, 'debug_page_html']
    );
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
    ?>
    <div class="wrap">
        <h1>Auto-Internal Linker Settings</h1>
        <p>Manage your internal linking keywords easily.</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>URL</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="keywords-list">
                <?php
                $links = get_option('auto_internal_links', []);
                if (!empty($links)) {
                    foreach ($links as $keyword => $url) {
                        echo '<tr>
                            <td contenteditable="true" class="edit-keyword">' . esc_html($keyword) . '</td>
                            <td contenteditable="true" class="edit-url">' . esc_url($url) . '</td>
                            <td><button class="remove-keyword button button-secondary" data-keyword="' . esc_attr($keyword) . '">Remove</button></td>
                        </tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">No keywords added yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <h3>Add New Keyword</h3>
        <input type="text" id="new-keyword" placeholder="Enter keyword" />
        <input type="url" id="new-url" placeholder="Enter URL" />
        <button id="add-keyword" class="button button-primary">Add Keyword</button>

        <div id="message-box"></div> <!-- Success/Error Message Box -->
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
    $links = get_transient('auto_internal_links_cache') ?: get_option('auto_internal_links', []);

    if (!empty($links)) {
        foreach ($links as $keyword => $url) {
            if (strpos($content, $keyword) !== false) { // Process only if the keyword exists
                $content = preg_replace_callback(
                    "/\b(" . preg_quote($keyword, '/') . ")\b(?![^<]*>|[^<>]*<\/a>)/i", 
                    function ($match) use ($url) {
                        return '<a href="' . esc_url($url) . '">' . esc_html($match[1]) . '</a>';
                    }, 
                    $content, 1
                );
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
    $user = get_userdata($user_id);
    $admin_email = get_option('admin_email');

    // Insert log into database
    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'action'  => sanitize_text_field($action),
    ]);

    // Send email notification to admin
    $subject = "🔔 Auto-Internal Linker Settings Changed";
    $message = "Hello,\n\nThe Auto-Internal Linker settings have been updated.\n\n";
    $message .= "Action: " . sanitize_text_field($action) . "\n";
    $message .= "By: " . esc_html($user->user_login) . "\n";
    $message .= "Timestamp: " . current_time('mysql') . "\n\n";
    $message .= "If this wasn't you, please review the changes immediately.\n\n";
    $message .= "Best Regards,\nYour WordPress Team";

    wp_mail($admin_email, $subject, $message);
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

public function create_audit_log_page() {
    add_submenu_page(
        'auto-internal-linker',
        'Audit Log',
        'Audit Log',
        'manage_options',
        'auto-internal-linker-log',
        [$this, 'display_audit_log']
    );
}

public function display_audit_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC");

    ?>
    <div class="wrap">
        <h1>Audit Log</h1>
        <table class="widefat">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html(get_userdata($log->user_id)->user_login); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->timestamp); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('admin_menu', [$this, 'create_audit_log_page']);

public function plugin_activation() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        error_message TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_action('wp_mail_failed', function($wp_error) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';

    $error_data = $wp_error->get_error_data();
    
    $wpdb->insert($table_name, [
        'recipient' => isset($error_data['to']) ? implode(', ', $error_data['to']) : 'Unknown',
        'subject'   => isset($error_data['subject']) ? $error_data['subject'] : 'Unknown',
        'message'   => isset($error_data['message']) ? $error_data['message'] : 'Unknown',
        'error_message' => $wp_error->get_error_message(),
    ]);
});

public function display_email_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 10");

    echo '<div class="wrap"><h2>Email Error Logs</h2>';
    if ($logs) {
        echo '<table class="widefat">
                <thead>
                    <tr><th>Recipient</th><th>Subject</th><th>Error</th><th>Time</th><th>Action</th></tr>
                </thead>
                <tbody>';
        foreach ($logs as $log) {
            echo "<tr>
                <td>{$log->recipient}</td>
                <td>{$log->subject}</td>
                <td>{$log->error_message}</td>
                <td>{$log->timestamp}</td>
                <td><button class='button resend-email' data-id='{$log->id}'>Resend</button></td>
            </tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No email errors logged.</p>';
    }
    echo '</div>';

    // Add JavaScript for AJAX
    echo '<script>
        document.querySelectorAll(".resend-email").forEach(button => {
            button.addEventListener("click", function() {
                let emailId = this.getAttribute("data-id");
                let btn = this;
                btn.textContent = "Resending...";
                fetch(ajaxurl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "action=resend_failed_email&email_id=" + emailId
                })
                .then(response => response.json())
                .then(data => {
                    btn.textContent = data.success ? "Resent" : "Failed";
                    alert(data.message);
                });
            });
        });
    </script>';
}


// Add to admin menu
public function create_email_log_page() {
    add_submenu_page(
        'auto-internal-linker',
        'Email Logs',
        'Email Logs',
        'manage_options',
        'auto-internal-linker-email-logs',
        [$this, 'display_email_logs']
    );
}
add_action('admin_menu', [$this, 'create_email_log_page']);

add_action('wp_ajax_resend_failed_email', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';
    $email_id = intval($_POST['email_id']);

    $email = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $email_id");

    if (!$email) {
        wp_send_json(['success' => false, 'message' => 'Email not found.']);
    }

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $sent = wp_mail($email->recipient, $email->subject, $email->message, $headers);

    if ($sent) {
        $wpdb->delete($table_name, ['id' => $email_id]); // Remove from error log
        wp_send_json(['success' => true, 'message' => 'Email resent successfully!']);
    } else {
        wp_send_json(['success' => false, 'message' => 'Failed to resend email.']);
    }
});

add_action('admin_menu', function() {
    add_submenu_page(
        'auto-internal-linker', 
        'Email Statistics', 
        'Email Stats', 
        'manage_options', 
        'auto-internal-linker-email-stats', 
        'auto_internal_linker_email_stats_page'
    );
});

function auto_internal_linker_email_stats_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';

    // Get selected date range (default: Last 7 days)
    $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '7';

    if ($date_range === 'all') {
        $where_clause = "1=1"; // No date filter
    } else {
        $days = intval($date_range);
        $where_clause = "timestamp >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }

    // Fetch email log data
    $results = $wpdb->get_results("
        SELECT timestamp, email, status, error_message
        FROM $table_name 
        WHERE $where_clause
        ORDER BY timestamp DESC
    ");

    echo '<div class="wrap"><h1>Email Statistics</h1>';

    // Export buttons
    echo '<form method="POST" action="">';
    echo '<input type="hidden" name="export_data" value="1">';
    echo '<input type="hidden" name="date_range" value="' . esc_attr($date_range) . '">';
    echo '<button type="submit" name="export_csv" class="button button-primary">📄 Export as CSV</button>';
    echo ' ';
    echo '<button type="submit" name="export_pdf" class="button button-secondary">📑 Export as PDF</button>';
    echo '</form>';

    // Display data table
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Timestamp</th><th>Email</th><th>Status</th><th>Error Message</th></tr></thead><tbody>';
    foreach ($results as $row) {
        echo "<tr>
                <td>{$row->timestamp}</td>
                <td>{$row->email}</td>
                <td>" . ($row->status ? 'Sent' : 'Failed') . "</td>
                <td>{$row->error_message}</td>
              </tr>";
    }
    echo '</tbody></table></div>';
}
if (isset($_POST['export_csv'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';

    $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : '7';

    if ($date_range === 'all') {
        $where_clause = "1=1"; // No date filter
    } else {
        $days = intval($date_range);
        $where_clause = "timestamp >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }

    // Fetch data
    $results = $wpdb->get_results("
        SELECT timestamp, email, status, error_message
        FROM $table_name 
        WHERE $where_clause
        ORDER BY timestamp DESC
    ");

    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email_stats.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp', 'Email', 'Status', 'Error Message']);

    foreach ($results as $row) {
        fputcsv($output, [$row->timestamp, $row->email, $row->status ? 'Sent' : 'Failed', $row->error_message]);
    }

    fclose($output);
    exit;
}






add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'settings_page_auto-internal-linker-email-stats') {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }
});

function auto_internal_linker_settings_page() {
    ?>
    <div class="wrap">
        <h1>Auto Internal Linker Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auto_internal_linker_settings');
            do_settings_sections('auto_internal_linker');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function auto_internal_linker_register_settings() {
    register_setting('auto_internal_linker_settings', 'auto_internal_linker_email_reports');
    register_setting('auto_internal_linker_settings', 'auto_internal_linker_report_frequency');
    register_setting('auto_internal_linker_settings', 'auto_internal_linker_report_recipients');

    add_settings_section('auto_internal_linker_section', 'Email Report Settings', null, 'auto_internal_linker');

    add_settings_field(
        'auto_internal_linker_email_reports',
        'Enable Email Reports',
        function () {
            $value = get_option('auto_internal_linker_email_reports', 'no');
            echo '<input type="checkbox" name="auto_internal_linker_email_reports" value="yes" ' . checked($value, 'yes', false) . '>';
        },
        'auto_internal_linker',
        'auto_internal_linker_section'
    );

    add_settings_field(
        'auto_internal_linker_report_frequency',
        'Report Frequency',
        function () {
            $value = get_option('auto_internal_linker_report_frequency', 'daily');
            echo '<select name="auto_internal_linker_report_frequency">
                    <option value="daily" ' . selected($value, 'daily', false) . '>Daily</option>
                    <option value="weekly" ' . selected($value, 'weekly', false) . '>Weekly</option>
                  </select>';
        },
        'auto_internal_linker',
        'auto_internal_linker_section'
    );

    add_settings_field(
        'auto_internal_linker_report_recipients',
        'Report Recipients',
        function () {
            $value = get_option('auto_internal_linker_report_recipients', get_option('admin_email'));
            echo '<input type="text" name="auto_internal_linker_report_recipients" value="' . esc_attr($value) . '" placeholder="Enter emails, separated by commas">';
        },
        'auto_internal_linker',
        'auto_internal_linker_section'
    );
}

add_action('admin_init', 'auto_internal_linker_register_settings');


function auto_internal_linker_schedule_cron() {
    if (!wp_next_scheduled('auto_internal_linker_update_cache')) {
        wp_schedule_event(time(), 'hourly', 'auto_internal_linker_update_cache');
    }
}
add_action('wp', 'auto_internal_linker_schedule_cron');

function auto_internal_linker_update_cache() {
    global $wpdb;

    $posts = get_posts([
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'post_type'      => 'post',
    ]);

    foreach ($posts as $post) {
        $cache_key = 'auto_internal_links_cache_' . md5($post->post_content);
        set_transient($cache_key, auto_internal_linker_apply_links($post->post_content), 24 * HOUR_IN_SECONDS);
    }
}
add_action('auto_internal_linker_update_cache', 'auto_internal_linker_update_cache');


function auto_internal_linker_generate_and_send_report() {
    $enabled = get_option('auto_internal_linker_email_reports', 'no');
    $frequency = get_option('auto_internal_linker_report_frequency', 'daily');

    if ($enabled !== 'yes') {
        return;
    }

    // Check if we need to send the report
    if ($frequency === 'weekly') {
        $last_sent = get_option('auto_internal_linker_last_report_sent', 0);
        if (time() - $last_sent < WEEK_IN_SECONDS) {
            return; // Skip if it's not time yet
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'auto_internal_linker_email_logs';
    
    // Fetch last 7 days' email logs
    $results = $wpdb->get_results("
        SELECT timestamp, email, status, error_message
        FROM $table_name 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY timestamp DESC
    ");

    if (!$results) {
        return; // No data to report
    }

    // Generate CSV
    $csv_file = auto_internal_linker_generate_csv($results);
    
    // Send email with CSV attachment
    auto_internal_linker_send_email($csv_file);

    // Update last sent time
    update_option('auto_internal_linker_last_report_sent', time());
}

function auto_internal_linker_generate_csv($data) {
    $file_path = WP_CONTENT_DIR . '/uploads/email_report.csv';
    $file = fopen($file_path, 'w');

    fputcsv($file, ['Timestamp', 'Email', 'Status', 'Error Message']);
    foreach ($data as $row) {
        fputcsv($file, [$row->timestamp, $row->email, $row->status ? 'Sent' : 'Failed', $row->error_message]);
    }

    fclose($file);
    return $file_path;
}

function auto_internal_linker_send_email($csv_path) {
    $recipient_emails = get_option('auto_internal_linker_report_recipients', get_option('admin_email'));
    $emails = array_map('trim', explode(',', $recipient_emails));

    // Validate emails
    $valid_emails = array_filter($emails, function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });

    if (empty($valid_emails)) {
        return;
    }

    $subject = "📊 Auto Internal Linker: Email Report";
    $message = "Here is your email report for the past 7 days.";

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Auto Internal Linker <no-reply@yourdomain.com>'
    ];

    $attachments = [$csv_path];

    foreach ($valid_emails as $email) {
        wp_mail($email, $subject, $message, $headers, $attachments);
    }
}

function auto_internal_linker_apply_cached_links($content) {
    if (is_admin()) {
        return $content; // Skip processing in the admin panel
    }

    $cache_key = 'auto_internal_links_cache_' . md5($content);
    $cached_content = get_transient($cache_key);

    if ($cached_content !== false) {
        return $cached_content; // Return cached version if available
    }

    $content = auto_internal_linker_apply_links($content);

    // Store in cache for 24 hours
    set_transient($cache_key, $content, 24 * HOUR_IN_SECONDS);

    return $content;
}

remove_filter('the_content', 'auto_internal_linker_apply_links');
add_filter('the_content', 'auto_internal_linker_apply_cached_links');

function auto_internal_linker_get_keywords() {
    static $cached_keywords = null;

    if ($cached_keywords === null) {
        global $wpdb;
        $options = get_option('auto_internal_links', []);
        $cached_keywords = is_array($options) ? $options : [];
    }

    return $cached_keywords;
}

function auto_internal_linker_register_debug_setting() {
    register_setting('auto_internal_linker_group', 'auto_internal_linker_debug_mode');
    add_settings_field(
        'auto_internal_linker_debug_mode',
        'Enable Debug Mode',
        'auto_internal_linker_debug_mode_callback',
        'auto_internal_linker_group',
        'default'
    );
}

function auto_internal_linker_debug_mode_callback() {
    $debug_enabled = get_option('auto_internal_linker_debug_mode', 0);
    ?>
    <input type="checkbox" name="auto_internal_linker_debug_mode" value="1" <?php checked(1, $debug_enabled, true); ?>>
    <label>Enable Debugging (Logs performance data in debug.log)</label>
    <?php
}

add_action('admin_init', 'auto_internal_linker_register_debug_setting');

function auto_internal_linker_apply_links_debug($content) {
    $start_time = microtime(true);
    $start_memory = memory_get_usage();

    $content = auto_internal_linker_apply_links($content); // Process links

    $execution_time = round(microtime(true) - $start_time, 4);
    $memory_usage = round((memory_get_usage() - $start_memory) / 1024, 2); // KB

    auto_internal_linker_log_debug("Linking executed in {$execution_time} sec, using {$memory_usage} KB memory.");

    return $content;
}

remove_filter('the_content', 'auto_internal_linker_apply_links');
add_filter('the_content', 'auto_internal_linker_apply_links_debug');

function ail_log_error($message) {
    $log_file = WP_CONTENT_DIR . '/ail-debug.log'; // Log file location
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[$timestamp] $message" . PHP_EOL;
    error_log($formatted_message, 3, $log_file);
}

public function debug_page_html() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $log_file = WP_CONTENT_DIR . '/ail-debug.log';
    $logs = file_exists($log_file) ? array_reverse(array_slice(file($log_file), -20)) : [];

    echo '<div class="wrap"><h1>Auto-Internal Linker Debug Log</h1>';
    echo '<pre>' . esc_html(implode("", $logs)) . '</pre>';
    echo '</div>';
}

// Function to get keywords with caching
function ail_get_keywords() {
    if (is_multisite()) {
        return get_site_option('auto_internal_links', []);
    } else {
        return get_option('auto_internal_links', []);
    }
}


function ail_update_keywords($new_keywords) {
    update_option('auto_internal_links', $new_keywords);
    delete_transient('ail_keywords_cache');
    ail_log("Cache cleared: Keywords updated.");
}


// Clear cache when updating keywords
function ail_update_keywords($new_keywords) {
    update_option('auto_internal_links', $new_keywords);
    delete_transient('ail_keywords_cache'); // Clear cache after update
}

function ail_replace_keywords($content) {
    $keywords = ail_get_keywords();
    
    foreach ($keywords as $keyword => $url) {
        if (strpos($content, $keyword) !== false) {
            ail_log("Replacing keyword: '$keyword' with link: $url");
        }
        $content = str_replace($keyword, '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content);
    }

    return $content;
}


function ail_filter_content_once($content) {
    static $processed = false;

    if ($processed) {
        return $content; // Prevent duplicate filtering
    }

    $processed = true;
    return ail_replace_keywords($content);
}

add_filter('the_content', 'ail_filter_content_once', 10);

function ail_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_file = WP_CONTENT_DIR . '/ail_debug.log';
        $time = date("Y-m-d H:i:s");
        file_put_contents($log_file, "[$time] $message" . PHP_EOL, FILE_APPEND);
    }
}

function ail_safe_get_option($option_name) {
    global $wpdb;

    $result = get_option($option_name, []);

    if ($result === false) {
        ail_log("Database error: Failed to retrieve option '$option_name'");
    }

    return $result;
}

function ail_register_multisite_setting() {
    if (is_multisite()) {
        register_setting('auto_internal_linker_group', 'ail_use_network_settings');
    }
}
add_action('admin_init', 'ail_register_multisite_setting');

function ail_settings_page_html() {
    ?>
    <div class="wrap">
        <h1>Auto-Internal Linker Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auto_internal_linker_group');
            do_settings_sections('auto_internal_linker_group');
            ?>
            
            <?php if (is_multisite()): ?>
                <label>
                    <input type="checkbox" name="ail_use_network_settings" value="1" <?php checked(1, get_option('ail_use_network_settings', 0)); ?>>
                    Use Network-Wide Settings
                </label>
            <?php endif; ?>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function ail_update_network_keywords($new_keywords) {
    if (is_multisite()) {
        update_site_option('auto_internal_links', $new_keywords);

        // Clear cache on all subsites
        $sites = get_sites();
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            delete_transient('ail_keywords_cache');
            restore_current_blog();
        }

        ail_log("Network-wide keywords updated, cache cleared on all subsites.");
    } else {
        update_option('auto_internal_links', $new_keywords);
        delete_transient('ail_keywords_cache');
    }
}

function ail_activate() {
    if (is_multisite()) {
        add_site_option('auto_internal_links', []);
        add_site_option('ail_use_network_settings', 0);
    } else {
        add_option('auto_internal_links', []);
    }
}
register_activation_hook(__FILE__, 'ail_activate');

function ail_apply_internal_links_builder_support($content) {
    if (is_admin() || empty($content)) {
        return $content;
    }

    // List of filters used by popular page builders
    $filters = [
        'the_content',        // Standard WP content
        'elementor/widget/render_content', // Elementor
        'wpb_widget_content', // WPBakery
        'et_builder_render_layout', // Divi
        'fl_builder_render_content', // Beaver Builder
        'kc_content', // King Composer
    ];

    foreach ($filters as $filter) {
        add_filter($filter, 'ail_apply_internal_links', 10);
    }

    return $content;
}

add_action('init', 'ail_apply_internal_links_builder_support');

function ail_get_supported_post_types() {
    $default_post_types = ['post', 'page'];
    
    // Get all registered post types
    $all_post_types = get_post_types(['public' => true], 'names');
    
    // Exclude unnecessary post types
    $excluded_post_types = ['attachment', 'revision', 'nav_menu_item'];
    
    return array_diff(array_merge($default_post_types, $all_post_types), $excluded_post_types);
}

function ail_apply_internal_links($content) {
    $links = get_option('auto_internal_links', []);
    if (empty($links)) {
        return $content;
    }

    // Skip linking inside shortcodes
    $content = preg_replace_callback('/\[.*?\]/', function($matches) {
        return esc_html($matches[0]); 
    }, $content);

    // Apply links normally
    foreach ($links as $keyword => $url) {
        $content = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/i", '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content, 1);
    }

    return $content;
}

function ail_apply_internal_links_woocommerce($content) {
    if (is_admin() || empty($content)) {
        return $content;
    }

    // Apply internal linking
    return ail_apply_internal_links($content);
}

// Apply links to product descriptions
add_filter('woocommerce_short_description', 'ail_apply_internal_links_woocommerce', 10);
add_filter('the_content', 'ail_apply_internal_links_woocommerce', 10);

function ail_sanitize_content($content) {
    return preg_replace_callback('/<[^>]+>|(\b\w+\b)/', function($matches) {
        return isset($matches[1]) ? ail_apply_internal_links($matches[1]) : $matches[0];
    }, $content);
}

function ail_exclude_woocommerce_pages($content) {
    if (is_cart() || is_checkout() || is_account_page()) {
        return $content; // No changes for these pages
    }
    return ail_apply_internal_links($content);
}

add_filter('the_content', 'ail_exclude_woocommerce_pages', 10);

function ail_get_cached_links() {
    $cached_links = get_transient('ail_cached_links');
    
    if ($cached_links === false) {
        $cached_links = get_option('auto_internal_links', []);
        set_transient('ail_cached_links', $cached_links, HOUR_IN_SECONDS); // Cache for 1 hour
    }

    return $cached_links;
}

function ail_apply_internal_links_optimized($content) {
    $links = ail_get_cached_links();
    
    if (!empty($links)) {
        foreach ($links as $keyword => $url) {
            $content = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/i", '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content, 1);
        }
    }

    return $content;
}
add_filter('the_content', 'ail_apply_internal_links_optimized', 10);

function ail_fetch_keywords_from_db() {
    global $wpdb;
    $results = $wpdb->get_results("SELECT keyword, url FROM {$wpdb->prefix}auto_internal_links", ARRAY_A);

    $links = [];
    foreach ($results as $row) {
        $links[$row['keyword']] = $row['url'];
    }

    return $links;
}

function ail_apply_limited_links($content) {
    $links = ail_get_cached_links();
    $limit = 3; // Maximum links per post
    $count = 0;

    if (!empty($links)) {
        foreach ($links as $keyword => $url) {
            if ($count >= $limit) break;
            if (strpos($content, $keyword) !== false) {
                $content = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/i", '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>', $content, 1);
                $count++;
            }
        }
    }

    return $content;
}
add_filter('the_content', 'ail_apply_limited_links', 10);


}

AutoInternalLinker::get_instance();
