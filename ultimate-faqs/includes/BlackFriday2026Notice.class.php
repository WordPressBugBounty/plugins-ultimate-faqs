<?php
/** The one-time UFAQ Black Friday 2026 upgrade notice. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ewdufaqBlackFriday2026Notice {
	// Fixed EST (UTC-5), independent of the WordPress site's timezone.
	const START = 1795669260; // November 26, 2026 at 00:01:00 EST.
	const END   = 1796101200; // December 1, 2026 at 00:00:00 EST (exclusive).

	const HIDDEN  = 'ewd-ufaq-bf2026-hidden';
	const SNOOZED = 'ewd-ufaq-bf2026-snoozed';
	const ACTION  = 'ewd_ufaq_bf2026_dismiss';
	const HANDLE  = 'ewd-ufaq-bf2026-notice';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'dismiss' ) );
	}

	protected function now() {
		return time();
	}

	private function in_window( $now ) {
		return $now >= self::START && $now < self::END;
	}

	private function should_show() {
		// Match admin_notices: every site admin screen, but not Network or User Admin.
		if ( ! $this->in_window( $this->now() ) || ! is_blog_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( false !== get_transient( self::HIDDEN ) || false !== get_transient( self::SNOOZED ) ) {
			return false;
		}

		global $ewd_ufaq_controller;
		$entitlement = $ewd_ufaq_controller->permissions->get_entitlement();
		// A trial is not a paid purchase. Current, cached and transitional paid access are.
		return ! in_array( $entitlement['effective_status'], array( 'valid', 'valid_cached', 'legacy_transition' ), true );
	}

	public function enqueue_assets() {
		if ( ! $this->should_show() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, EWD_UFAQ_PLUGIN_URL . '/assets/css/black-friday-2026-notice.css', array(), EWD_UFAQ_VERSION );
		wp_enqueue_script( self::HANDLE, EWD_UFAQ_PLUGIN_URL . '/assets/js/black-friday-2026-notice.js', array( 'jquery' ), EWD_UFAQ_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'ewd_ufaq_bf2026_notice',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'nonce'  => wp_create_nonce( self::ACTION ),
				'action' => self::ACTION,
				'error'  => __( 'Could not hide this notice. Please try again.', 'ultimate-faqs' ),
			)
		);
	}

	public function render() {
		if ( ! $this->should_show() ) {
			return;
		}
		?>
		<div class="notice notice-info ewd-ufaq-bf2026-notice">
			<img class="ewd-ufaq-bf2026-icon" src="<?php echo esc_url( EWD_UFAQ_PLUGIN_URL . '/assets/img/ewd-ufaq-icon.png' ); ?>" width="100" height="100" alt="">
			<div class="ewd-ufaq-bf2026-content">
				<p><?php esc_html_e( 'Take advantage of our Black Friday sale to upgrade to Ultimate FAQs Premium! Use code BF2026 at checkout to get 20% off the regular price. The sale ends at 11:59:59 PM EST on November 30.', 'ultimate-faqs' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( 'https://www.etoilewebdesign.com/license-payment/?Selected=UFAQ&Quantity=1&utm_source=plugin_admin_bf2026' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Premium', 'ultimate-faqs' ); ?></a></p>
				<p class="ewd-ufaq-bf2026-actions">
					<button type="button" class="button ewd-ufaq-bf2026-dismiss" data-dismissal="temporary"><?php esc_html_e( 'Hide Temporarily', 'ultimate-faqs' ); ?></button>
					<button type="button" class="button ewd-ufaq-bf2026-dismiss" data-dismissal="permanent"><?php esc_html_e( "Don't Show Again", 'ultimate-faqs' ); ?></button>
					<span class="ewd-ufaq-bf2026-status" role="status" aria-live="polite"></span>
				</p>
			</div>
		</div>
		<?php
	}

	public function dismiss() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to hide this notice.', 'ultimate-faqs' ) ), 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the two exact values below are accepted.
		$mode = isset( $_POST['dismissal'] ) && is_string( $_POST['dismissal'] ) ? wp_unslash( $_POST['dismissal'] ) : '';
		if ( ! in_array( $mode, array( 'temporary', 'permanent' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid dismissal choice.', 'ultimate-faqs' ) ), 400 );
		}

		$now = $this->now();
		if ( $this->in_window( $now ) ) {
			$remaining = self::END - $now;
			$key       = 'permanent' === $mode ? self::HIDDEN : self::SNOOZED;
			$ttl       = 'permanent' === $mode ? $remaining : min( HOUR_IN_SECONDS, $remaining );
			// A stale tab's snooze cannot overwrite the permanent dismissal.
			if ( ! set_transient( $key, 1, $ttl ) && false === get_transient( $key ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not hide this notice. Please try again.', 'ultimate-faqs' ) ), 500 );
			}
		}
		wp_send_json_success();
	}
}
