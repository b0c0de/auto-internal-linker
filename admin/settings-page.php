<?php
if (!defined('ABSPATH')) {
    exit;
}

class AutoInternalLinker_Settings {
    public static function settings_page_html() {
        $stored_links = get_option('auto_internal_links', '{}');
        $links = json_decode($stored_links, true);
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
                        <table id="keywords-table">
    <thead>
        <tr>
            <th>Keyword</th>
            <th>URL</th>
            <th>Max Links</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($links)): ?>
            <?php foreach ($links as $keyword => $data): ?>
                <tr>
                    <td><input type="text" class="keyword-input" value="<?php echo esc_attr($keyword); ?>"></td>
                    <td><input type="url" class="url-input" value="<?php echo esc_url($data['url']); ?>"></td>
                    <td><input type="number" class="limit-input" value="<?php echo esc_attr($data['limit']); ?>" min="1"></td>
                    <td><button type="button" class="remove-keyword button">Remove</button></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

                            <button type="button" id="add-keyword" class="button">Add Keyword</button>
                            <input type="hidden" name="auto_internal_links" id="auto_internal_links">
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const tableBody = document.querySelector("#keywords-table tbody");
                const addKeywordBtn = document.getElementById("add-keyword");
                const hiddenInput = document.getElementById("auto_internal_links");

                function updateHiddenInput() {
                    let data = {};
                    tableBody.querySelectorAll("tr").forEach(row => {
                        const keyword = row.querySelector(".keyword-input").value.trim();
                        const url = row.querySelector(".url-input").value.trim();
                        if (keyword && url) {
                            data[keyword] = url;
                        }
                    });
                    hiddenInput.value = JSON.stringify(data);
                }

                addKeywordBtn.addEventListener("click", function () {
                    const row = document.createElement("tr");
                    row.innerHTML = `
                        <td><input type="text" class="keyword-input"></td>
                        <td><input type="url" class="url-input"></td>
                        <td><button type="button" class="remove-keyword button">Remove</button></td>
                    `;
                    tableBody.appendChild(row);
                    updateHiddenInput();
                });

                tableBody.addEventListener("click", function (event) {
                    if (event.target.classList.contains("remove-keyword")) {
                        event.target.closest("tr").remove();
                        updateHiddenInput();
                    }
                });

                tableBody.addEventListener("input", updateHiddenInput);
            });
        </script>
        <?php
    }
}
