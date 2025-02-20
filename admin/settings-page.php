<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Settings {
    public static function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Auto-Internal Linker Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('auto_internal_linker_group');
                do_settings_sections('auto_internal_linker_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Keyword-Link Pairs</th>
                        <td>
                            <textarea name="auto_internal_links" rows="5" cols="50"><?php echo esc_textarea(get_option('auto_internal_links', '')); ?></textarea>
                            <p>Enter keyword-link pairs in JSON format: {"keyword": "https://example.com"}</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
