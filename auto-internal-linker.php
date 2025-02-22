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

    // Define the WHERE clause based on date range
    if ($date_range === 'all') {
        $where_clause = "1=1"; // No date filter
    } else {
        $days = intval($date_range);
        $where_clause = "timestamp >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }

    // Fetch total sent and failed emails
    $total_sent = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE error_message IS NULL AND $where_clause");
    $total_failed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE error_message IS NOT NULL AND $where_clause");

    // Fetch daily email stats
    $results = $wpdb->get_results("
        SELECT DATE(timestamp) as date, 
               COUNT(*) as total, 
               SUM(CASE WHEN error_message IS NULL THEN 1 ELSE 0 END) as sent, 
               SUM(CASE WHEN error_message IS NOT NULL THEN 1 ELSE 0 END) as failed
        FROM $table_name 
        WHERE $where_clause
        GROUP BY DATE(timestamp) 
        ORDER BY DATE(timestamp) ASC
    ");

    // Prepare data for the chart
    $dates = [];
    $sent_data = [];
    $failed_data = [];

    foreach ($results as $row) {
        $dates[] = $row->date;
        $sent_data[] = $row->sent;
        $failed_data[] = $row->failed;
    }

    echo '<div class="wrap"><h1>Email Statistics</h1>';

    // Dropdown for date range selection
    echo '<form method="GET" action="">';
    echo '<input type="hidden" name="page" value="auto_internal_linker_email_stats">';
    echo '<label for="date_range">Select Date Range: </label>';
    echo '<select name="date_range" onchange="this.form.submit()">';
    echo '<option value="7"' . selected($date_range, '7', false) . '>Last 7 Days</option>';
    echo '<option value="30"' . selected($date_range, '30', false) . '>Last 30 Days</option>';
    echo '<option value="all"' . selected($date_range, 'all', false) . '>All Time</option>';
    echo '</select>';
    echo '</form>';

    echo "<p><strong>Total Sent:</strong> $total_sent</p>";
    echo "<p><strong>Total Failed:</strong> $total_failed</p>";

    // Chart canvases
    echo '<h2>Email Success vs Failure</h2>';
    echo '<canvas id="emailStatsChart" width="400" height="200"></canvas>';
    
    echo '<h2>Daily Email Trends</h2>';
    echo '<canvas id="emailTrendsChart" width="400" height="200"></canvas>';

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx1 = document.getElementById('emailStatsChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Sent', 'Failed'],
                    datasets: [{
                        data: [<?php echo $total_sent; ?>, <?php echo $total_failed; ?>],
                        backgroundColor: ['#28a745', '#dc3545']
                    }]
                }
            });

            var ctx2 = document.getElementById('emailTrendsChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [
                        {
                            label: 'Sent Emails',
                            data: <?php echo json_encode($sent_data); ?>,
                            borderColor: '#28a745',
                            fill: false
                        },
                        {
                            label: 'Failed Emails',
                            data: <?php echo json_encode($failed_data); ?>,
                            borderColor: '#dc3545',
                            fill: false
                        }
                    ]
                }
            });
        });
    </script>
    <?php
    echo '</div>';
}




add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'settings_page_auto-internal-linker-email-stats') {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }
});


}

AutoInternalLinker::get_instance();
