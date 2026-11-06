<?php
/**
 * Plugin Name:       Auto-Internal Linker
 * Plugin URI:        https://github.com/b0c0de/auto-internal-linker
 * Description:       Automatically links keywords you define to internal URLs. One link per keyword per post, never inside headings or existing links.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Bojan Cvjetković
 * Author URI:        https://brisk-web-services.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       auto-internal-linker
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIL_Auto_Internal_Linker {

	const OPT_LINKS      = 'auto_internal_links';   // array( keyword => url ).
	const OPT_POST_TYPES = 'ail_post_types';        // array( 'post', 'page' ).
	const MENU_SLUG      = 'auto-internal-linker';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Front end.
		add_filter( 'the_content', array( $this, 'apply_links' ), 20 );

		// Admin.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_post_ail_add', array( $this, 'handle_add' ) );
		add_action( 'admin_post_ail_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_ail_save_settings', array( $this, 'handle_save_settings' ) );
	}

	/* ------------------------------------------------------------------
	 * Data helpers
	 * ---------------------------------------------------------------- */

	private function get_links() {
		$links = get_option( self::OPT_LINKS, array() );
		return is_array( $links ) ? $links : array();
	}

	private function get_post_types() {
		$types = get_option( self::OPT_POST_TYPES, array( 'post', 'page' ) );
		return is_array( $types ) ? $types : array( 'post', 'page' );
	}

	/* ------------------------------------------------------------------
	 * Front end: apply links
	 * ---------------------------------------------------------------- */

	public function apply_links( $content ) {
		if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! in_array( get_post_type(), $this->get_post_types(), true ) ) {
			return $content;
		}

		$links = $this->get_links();
		if ( empty( $links ) || '' === trim( $content ) ) {
			return $content;
		}

		// Do not link a page to itself.
		$current_url = untrailingslashit( get_permalink() );
		foreach ( $links as $keyword => $url ) {
			if ( untrailingslashit( $url ) === $current_url ) {
				unset( $links[ $keyword ] );
			}
		}
		if ( empty( $links ) ) {
			return $content;
		}

		// Longest keywords first so "internal linking tips" wins over "internal linking".
		$keywords = array_map( 'strval', array_keys( $links ) );
		usort(
			$keywords,
			function ( $a, $b ) {
				return mb_strlen( $b ) - mb_strlen( $a );
			}
		);

		$map = array(); // lower-case keyword => url.
		foreach ( $links as $keyword => $url ) {
			$map[ mb_strtolower( (string) $keyword ) ] = $url;
		}

		$quoted = array();
		foreach ( $keywords as $keyword ) {
			$quoted[] = preg_quote( $keyword, '/' );
		}
		$regex = '/(?<![\p{L}\p{N}_])(' . implode( '|', $quoted ) . ')(?![\p{L}\p{N}_])/iu';

		// Split into tags and text so we only touch text nodes.
		$parts = preg_split( '/(<[^>]*>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $content;
		}

		$skip_tags  = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'button', 'code', 'pre', 'textarea', 'select', 'option', 'figcaption' );
		$skip_depth = 0;
		$used       = array(); // keywords already linked in this post.

		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}

			// Tag token.
			if ( '<' === $part[0] ) {
				if ( preg_match( '/^<(\/?)([a-z0-9]+)/i', $part, $m ) ) {
					$tag = strtolower( $m[2] );
					if ( in_array( $tag, $skip_tags, true ) ) {
						if ( '/' === $m[1] ) {
							$skip_depth = max( 0, $skip_depth - 1 );
						} elseif ( '/>' !== substr( $part, -2 ) ) {
							$skip_depth++;
						}
					}
				}
				continue;
			}

			// Text token inside a skipped element.
			if ( $skip_depth > 0 ) {
				continue;
			}

			$parts[ $i ] = preg_replace_callback(
				$regex,
				function ( $match ) use ( &$used, $map ) {
					$key = mb_strtolower( $match[1] );
					if ( isset( $used[ $key ] ) || ! isset( $map[ $key ] ) ) {
						return $match[0];
					}
					$used[ $key ] = true;
					return '<a href="' . esc_url( $map[ $key ] ) . '">' . esc_html( $match[1] ) . '</a>';
				},
				$part
			);
		}

		return implode( '', $parts );
	}

	/* ------------------------------------------------------------------
	 * Admin: settings page
	 * ---------------------------------------------------------------- */

	public function add_settings_page() {
		add_options_page(
			__( 'Auto Internal Linker', 'auto-internal-linker' ),
			__( 'Auto Linker', 'auto-internal-linker' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$links      = $this->get_links();
		$saved_types = $this->get_post_types();
		$all_types  = get_post_types( array( 'public' => true ), 'objects' );
		unset( $all_types['attachment'] );

		$msg = isset( $_GET['ail_msg'] ) ? sanitize_key( wp_unslash( $_GET['ail_msg'] ) ) : '';
		$notices = array(
			'added'   => array( 'success', __( 'Keyword added.', 'auto-internal-linker' ) ),
			'deleted' => array( 'success', __( 'Keyword removed.', 'auto-internal-linker' ) ),
			'saved'   => array( 'success', __( 'Settings saved.', 'auto-internal-linker' ) ),
			'invalid' => array( 'error', __( 'Please enter a keyword and a valid URL.', 'auto-internal-linker' ) ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auto-Internal Linker', 'auto-internal-linker' ); ?></h1>

			<?php if ( isset( $notices[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notices[ $msg ][0] ); ?> is-dismissible">
					<p><?php echo esc_html( $notices[ $msg ][1] ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Keywords', 'auto-internal-linker' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Keyword', 'auto-internal-linker' ); ?></th>
						<th><?php esc_html_e( 'URL', 'auto-internal-linker' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Actions', 'auto-internal-linker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $links ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No keywords added yet.', 'auto-internal-linker' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $links as $keyword => $url ) : ?>
						<tr>
							<td><?php echo esc_html( $keyword ); ?></td>
							<td><?php echo esc_html( $url ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'ail_delete' ); ?>
									<input type="hidden" name="action" value="ail_delete">
									<input type="hidden" name="keyword" value="<?php echo esc_attr( $keyword ); ?>">
									<button type="submit" class="button button-secondary"><?php esc_html_e( 'Remove', 'auto-internal-linker' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Add keyword', 'auto-internal-linker' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ail_add' ); ?>
				<input type="hidden" name="action" value="ail_add">
				<input type="text" name="keyword" placeholder="<?php esc_attr_e( 'Keyword', 'auto-internal-linker' ); ?>" class="regular-text" required>
				<input type="url" name="url" placeholder="https://example.com/page/" class="regular-text" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'auto-internal-linker' ); ?></button>
			</form>

			<h2><?php esc_html_e( 'Apply links to', 'auto-internal-linker' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ail_save_settings' ); ?>
				<input type="hidden" name="action" value="ail_save_settings">
				<?php foreach ( $all_types as $type ) : ?>
					<label style="display:block;margin-bottom:4px;">
						<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $saved_types, true ) ); ?>>
						<?php echo esc_html( $type->labels->name ); ?>
					</label>
				<?php endforeach; ?>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'auto-internal-linker' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Admin: form handlers
	 * ---------------------------------------------------------------- */

	private function redirect_back( $msg ) {
		wp_safe_redirect(
			add_query_arg(
				'ail_msg',
				$msg,
				admin_url( 'options-general.php?page=' . self::MENU_SLUG )
			)
		);
		exit;
	}

	public function handle_add() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'auto-internal-linker' ) );
		}
		check_admin_referer( 'ail_add' );

		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$url     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( '' === $keyword || '' === $url ) {
			$this->redirect_back( 'invalid' );
		}

		$links             = $this->get_links();
		$links[ $keyword ] = $url;
		update_option( self::OPT_LINKS, $links );

		$this->redirect_back( 'added' );
	}

	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'auto-internal-linker' ) );
		}
		check_admin_referer( 'ail_delete' );

		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$links   = $this->get_links();

		if ( isset( $links[ $keyword ] ) ) {
			unset( $links[ $keyword ] );
			update_option( self::OPT_LINKS, $links );
		}

		$this->redirect_back( 'deleted' );
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'auto-internal-linker' ) );
		}
		check_admin_referer( 'ail_save_settings' );

		$types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
			: array();

		update_option( self::OPT_POST_TYPES, $types );

		$this->redirect_back( 'saved' );
	}
}

AIL_Auto_Internal_Linker::get_instance();
