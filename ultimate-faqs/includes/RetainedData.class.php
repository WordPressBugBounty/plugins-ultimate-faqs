<?php
/**
 * Retained-data privacy and draft lifecycle, independent of paid execution.
 *
 * @package ultimate-faqs
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ewdufaqRetainedData' ) ) {
	class ewdufaqRetainedData {
		const MAX_PER_SHARD      = 256;
		const NORMALIZER_VERSION = 1;
		const BATCH_SIZE         = 100;
		const CLEANUP_CURSOR     = 'ewd-ufaq-retained-cleanup-cursor-v1';
		const CLEAR_CURSOR       = 'ewd-ufaq-retained-clear-cursor-v1';
		const CLEAR_ERROR        = 'ewd-ufaq-retained-clear-error-v1';
		private static $instance;
		private static $privacy_menu_registered = false;

		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function __construct() {
			add_filter( 'pre_trash_post', array( $this, 'before_trash' ), 10, 2 );
			add_filter( 'pre_delete_post', array( $this, 'before_delete' ), 10, 3 );
			add_action( 'admin_post_ewd_ufaq_reject_ai_draft', array( $this, 'handle_reject' ) );
			add_action( 'init', array( $this, 'reconcile_schedule' ), 20 );
			add_action( 'ewd_ufaq_daily_analytics_cleanup', array( $this, 'cleanup' ) );
			add_action( 'ewd_ufaq_retained_cleanup_batch', array( $this, 'cleanup' ) );
			add_action( 'ewd_ufaq_retained_clear_batch', array( $this, 'clear_batch' ) );
			add_action( 'admin_post_ewd_ufaq_clear_analytics', array( $this, 'clear_analytics' ) );
			add_action( 'admin_menu', array( $this, 'register_privacy_menu' ), 20 );
			add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		}

		public function reconcile_schedule() {
			if ( ! wp_next_scheduled( 'ewd_ufaq_daily_analytics_cleanup' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ewd_ufaq_daily_analytics_cleanup' );
			}
		}

		/** A single bounded read; never returns a library-sized option list. */
		private function retained_rows( $after ) {
			global $wpdb;
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s) AND option_name > %s ORDER BY option_name ASC LIMIT %d",
					$wpdb->esc_like( 'ewd-ufaq-analytics-v1-' ) . '%',
					$wpdb->esc_like( 'ewd-ufaq-analytics-lock-' ) . '%',
					$wpdb->esc_like( 'ewd-ufaq-analytics-dropped-' ) . '%',
					is_string( $after ) ? $after : '',
					self::BATCH_SIZE
				)
			);
		}

		/** Compare raw bytes so cleanup cannot erase a replacement collector lock. */
		private function delete_retained_row( $row ) {
			global $wpdb;
			$changed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s", $row->option_name, $row->option_value ) );
			self::invalidate_option_cache( $row->option_name );
			return 1 === $changed;
		}

		public function cleanup() {
			$settings  = get_option( 'ewd-ufaq-settings', array() );
			$retention = is_array( $settings ) && isset( $settings['analytics-retention-days'] ) ? (int) $settings['analytics-retention-days'] : 30;
			$retention = in_array( $retention, array( 7, 30, 60, 90 ), true ) ? $retention : 30;
			$cutoff    = gmdate( 'Y-m-d', time() - $retention * DAY_IN_SECONDS );
			$rows      = $this->retained_rows( get_option( self::CLEANUP_CURSOR, '' ) );
			if ( ! is_array( $rows ) || $GLOBALS['wpdb']->last_error ) {
				$this->schedule_batch( 'ewd_ufaq_retained_cleanup_batch', 60 );
				return;
			}
			foreach ( $rows as $row ) {
				$date    = substr( $row->option_name, -10 );
				$expired = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $date < $cutoff;
				if ( 0 === strpos( $row->option_name, 'ewd-ufaq-analytics-lock-' ) ) {
					$parts   = explode( '|', $row->option_value );
					$expired = 2 === count( $parts ) && ctype_digit( $parts[1] ) && (int) $parts[1] < time();
				}
				if ( $expired ) {
					if ( ! $this->delete_retained_row( $row ) && $GLOBALS['wpdb']->last_error ) {
						$this->schedule_batch( 'ewd_ufaq_retained_cleanup_batch', 60 );
						return;
					}
				}
			}
			$this->continue_batch( self::CLEANUP_CURSOR, 'ewd_ufaq_retained_cleanup_batch', $rows );
		}

		private function continue_batch( $option, $hook, $rows ) {
			if ( self::BATCH_SIZE === count( $rows ) ) {
				$last = end( $rows );
				update_option( $option, $last->option_name, false );
				self::invalidate_option_cache( $option );
				$this->schedule_batch( $hook, 1 );
			} else {
				delete_option( $option );
				wp_clear_scheduled_hook( $hook );
			}
		}

		private function schedule_batch( $hook, $delay ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_single_event( time() + $delay, $hook );
			}
		}

		public function clear_batch() {
			$cursor = get_option( self::CLEAR_CURSOR, false );
			if ( false === $cursor ) {
				return false;
			}
			$rows = $this->retained_rows( $cursor );
			if ( ! is_array( $rows ) || $GLOBALS['wpdb']->last_error ) {
				update_option( self::CLEAR_ERROR, 1, false );
				$this->schedule_batch( 'ewd_ufaq_retained_clear_batch', 60 );
				return false;
			}
			foreach ( $rows as $row ) {
				if ( ! $this->delete_retained_row( $row ) ) {
					// Retry the same bounded range after a concurrent replacement or DB failure.
					update_option( self::CLEAR_ERROR, 1, false );
					$this->schedule_batch( 'ewd_ufaq_retained_clear_batch', 60 );
					return false;
				}
			}
			$this->continue_batch( self::CLEAR_CURSOR, 'ewd_ufaq_retained_clear_batch', $rows );
			delete_option( self::CLEAR_ERROR );
			delete_transient( 'ewd_ufaq_content_gaps_v1' );
			return false === get_option( self::CLEAR_CURSOR, false );
		}

		public function clear_analytics() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to clear FAQ analytics.', 'ultimate-faqs' ), 403 );
			}
			check_admin_referer( 'ewd_ufaq_clear_analytics' );
			update_option( self::CLEAR_CURSOR, '', false );
			self::invalidate_option_cache( self::CLEAR_CURSOR );
			if ( '' !== get_option( self::CLEAR_CURSOR, false ) ) {
				wp_die( esc_html__( 'Analytics deletion could not be started. Please retry.', 'ultimate-faqs' ) );
			}
			delete_option( 'ewd-ufaq-search-data' );
			$complete = $this->clear_batch();
			$url      = admin_url( 'edit.php?post_type=ufaq&page=ewd-ufaq-analytics-privacy' );
			wp_safe_redirect( add_query_arg( $complete ? 'ewd_ufaq_analytics_cleared' : 'ewd_ufaq_analytics_pending', '1', $url ) );
			exit;
		}

		public function register_privacy_menu() {
			if ( self::$privacy_menu_registered ) {
				return;
			}
			self::$privacy_menu_registered = true;
			add_submenu_page( 'edit.php?post_type=ufaq', __( 'FAQ Analytics Privacy', 'ultimate-faqs' ), __( 'Analytics Privacy', 'ultimate-faqs' ), 'manage_options', 'ewd-ufaq-analytics-privacy', array( $this, 'render_privacy_screen' ) );
		}

		public static function render_privacy_screen() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to clear FAQ analytics.', 'ultimate-faqs' ), 403 );
			}
			echo '<div class="wrap"><h1>' . esc_html__( 'FAQ Analytics Privacy', 'ultimate-faqs' ) . '</h1><p>' . esc_html__( 'You can delete retained FAQ analytics without Premium Helper. FAQ content and AI suppression records are preserved.', 'ultimate-faqs' ) . '</p>';
			if ( false !== get_option( self::CLEAR_CURSOR, false ) ) {
				echo '<p role="status">' . esc_html__( 'Analytics deletion is continuing in bounded background batches. Reload to check progress.', 'ultimate-faqs' ) . '</p>';
			}
			if ( get_option( self::CLEAR_ERROR ) ) {
				echo '<p role="alert">' . esc_html__( 'Analytics deletion encountered a database or concurrent-write conflict. It will retry; you can also restart deletion using the button below.', 'ultimate-faqs' ) . '</p>';
			}
			self::render_clear_form();
			echo '</div>';
		}

		public static function render_clear_form() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$confirm = 'return window.confirm(' . wp_json_encode( __( 'Permanently delete retained FAQ analytics and legacy search logs? This cannot be undone.', 'ultimate-faqs' ) ) . ');';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="' . esc_attr( $confirm ) . '"><input type="hidden" name="action" value="ewd_ufaq_clear_analytics" />';
			wp_nonce_field( 'ewd_ufaq_clear_analytics' );
			echo '<p>' . esc_html__( 'This permanently deletes retained analytics and legacy search logs. Stop collection first if no new records should be collected.', 'ultimate-faqs' ) . '</p><button class="button" type="submit">' . esc_html__( 'Clear governed analytics', 'ultimate-faqs' ) . '</button></form>';
		}

		public function add_policy_content() {
			if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
				return;
			}
			$content  = '<p>' . esc_html__( 'When governed FAQ analytics is enabled by an administrator, this site stores normalized FAQ search text and aggregate signals for no-result searches, answer opens, and helpfulness responses. These records are stored locally in daily buckets for the administrator-selected retention period of 7, 30, 60, or 90 days and can be deleted by an administrator. Raw visitor IP addresses and user-agent strings are not retained in these analytics records, and temporary anti-abuse fingerprints are not kept as analytics data.', 'ultimate-faqs' ) . '</p>';
			$content .= '<p>' . esc_html__( 'When an administrator requests an AI draft, or enables automatic draft generation, the normalized content-gap query and bounded administrator-approved public source context are sent through the WordPress AI Client to the AI provider configured for this site. The configured provider processes that data under its own terms and privacy policy. Generated content is saved only as a draft for administrator review and is never published automatically.', 'ultimate-faqs' ) . '</p>';
			$content .= '<p>' . esc_html__( 'The separate legacy AI Assist feature uses the Etoile Web Design relay and OpenAI only after an authorized administrator accepts its disclosure and requests generation. Administrators control whether analytics, automatic generation, source context, and legacy AI Assist are used.', 'ultimate-faqs' ) . '</p>';
			$content .= '<p>' . esc_html__( 'Administrators can delete retained analytics and legacy search logs after Premium Helper is removed. Rejected AI-draft suppression records remain until deliberately reversed. If both plugins are removed, run the deliberate analytics purge first; no PHP cleanup can run after both code owners are absent.', 'ultimate-faqs' ) . '</p>';
			wp_add_privacy_policy_content( __( 'Ultimate FAQs', 'ultimate-faqs' ), wp_kses_post( $content ) );
		}

		public static function is_suppressed( $key ) {

			$records = get_option( self::option_name( $key ), array() );

			return isset( $records[ $key ] ) ? $records[ $key ] : false;
		}

		public static function is_full() {

			foreach ( str_split( '0123456789abcdef' ) as $nibble ) {

				if ( count( get_option( 'ewd-ufaq-ai-suppressions-v1-0' . $nibble, array() ) ) >= self::MAX_PER_SHARD ) {
					return true; }
			}

			return false;
		}

		public static function suppress( $key, $locale, $query, $reason, $draft_id = 0 ) {

			$key    = sanitize_text_field( $key );
			$reason = sanitize_key( $reason );

			if ( ! preg_match( '/^[a-f0-9]{64}$/', $key ) || ! in_array( $reason, array( 'rejected', 'deleted', 'administrator-suppressed' ), true ) ) {

				return new WP_Error( 'invalid_suppression', __( 'The content-gap suppression is invalid.', 'ultimate-faqs' ) );
			}

			$name   = self::option_name( $key );
			$record = array(
				'key'                  => $key,
				'normalizer_version'   => self::NORMALIZER_VERSION,
				'locale'               => sanitize_key( $locale ),
				'representative_query' => function_exists( 'mb_substr' ) ? mb_substr( sanitize_text_field( $query ), 0, 200 ) : substr( sanitize_text_field( $query ), 0, 200 ),
				'suppressed_at'        => time(),
				'user_id'              => get_current_user_id(),
				'reason'               => $reason,
				'last_draft_id'        => absint( $draft_id ),
			);

			for ( $try = 0; $try < 50; $try++ ) {
				$missing = new stdClass();
				$current = get_option( $name, $missing );
				$records = $missing === $current || ! is_array( $current ) ? array() : $current;

				if ( isset( $records[ $key ] ) ) {
					if ( self::committed_contains( $name, $key ) ) {
						return true;
					}

					self::invalidate_option_cache( $name );
					usleep( wp_rand( 1000, 5000 ) );
					continue;
				}

				if ( count( $records ) >= self::MAX_PER_SHARD ) {
					return new WP_Error( 'suppression_cap', __( 'The FAQ suppression store is full. Existing suppressions were preserved and automatic generation has been stopped.', 'ultimate-faqs' ) );
				}

				$updated         = $records;
				$updated[ $key ] = $record;
				$saved           = $missing === $current
					? self::add_exact( $name, $updated )
					: self::compare_and_swap( $name, $current, $updated );

				if ( $saved && self::committed_contains( $name, $key ) ) {
					self::invalidate_gap_cache();

					return true;
				}

				usleep( wp_rand( 1000, 5000 ) );
			}

			return new WP_Error( 'suppression_write_failed', __( 'The content-gap suppression could not be saved, so the FAQ draft was preserved.', 'ultimate-faqs' ) );
		}

		public static function unsuppress( $key ) {

			$key = sanitize_text_field( $key );

			if ( ! preg_match( '/^[a-f0-9]{64}$/', $key ) ) {
				return new WP_Error( 'invalid_suppression', __( 'The content-gap suppression is invalid.', 'ultimate-faqs' ) );
			}

			$name = self::option_name( $key );

			for ( $try = 0; $try < 50; $try++ ) {
				$missing = new stdClass();
				$current = get_option( $name, $missing );

				if ( $missing === $current || ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
					if ( ! self::committed_contains( $name, $key ) ) {
						return true;
					}

					self::invalidate_option_cache( $name );
					usleep( wp_rand( 1000, 5000 ) );
					continue;
				}

				$updated = $current;
				unset( $updated[ $key ] );

				if ( self::compare_and_swap( $name, $current, $updated ) && ! self::committed_contains( $name, $key ) ) {
					self::invalidate_gap_cache();

					return true;
				}

				usleep( wp_rand( 1000, 5000 ) );
			}

			return new WP_Error( 'suppression_write_failed', __( 'The content-gap suppression could not be removed.', 'ultimate-faqs' ) );
		}

		public function before_trash( $trash, $post ) {

			if ( ! $this->is_eligible_ai_draft( $post ) ) {
				return $trash;
			}

			$result = $this->suppress_draft( $post, 'deleted' );

			if ( is_wp_error( $result ) ) {
				return false;
			}

			wp_delete_post( $post->ID, true );

			return false;
		}

		public function before_delete( $delete, $post, $force_delete ) {

			if ( ! $this->is_eligible_ai_draft( $post ) ) {
				return $delete;
			}

			$result = $this->suppress_draft( $post, 'deleted' );

			return is_wp_error( $result ) ? false : $delete;
		}

		private function suppress_draft( $post, $reason ) {

			$key = get_post_meta( $post->ID, '_ewd_ufaq_ai_gap_key', true );

			return self::suppress(
				$key,
				get_post_meta( $post->ID, '_ewd_ufaq_ai_locale', true ),
				$post->post_title,
				$reason,
				$post->ID
			);
		}

		private function is_eligible_ai_draft( $post ) {

			if (
			! $post instanceof WP_Post
			|| EWD_UFAQ_FAQ_POST_TYPE !== $post->post_type
			|| 'draft' !== $post->post_status
			|| ! get_post_meta( $post->ID, '_ewd_ufaq_ai_generated', true )
			) {
				return false;
			}

			return (bool) preg_match( '/^[a-f0-9]{64}$/', (string) get_post_meta( $post->ID, '_ewd_ufaq_ai_gap_key', true ) );
		}

		public function handle_reject() {

			$this->authorize( 'ewd_ufaq_reject_ai_draft' );
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$post    = get_post( $post_id );

			if ( ! $this->is_eligible_ai_draft( $post ) ) {

				$this->redirect_result( new WP_Error( 'invalid_draft', __( 'The AI FAQ draft is invalid.', 'ultimate-faqs' ) ) );
			}
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				$this->redirect_result( new WP_Error( 'delete_forbidden', __( 'You are not allowed to reject this AI FAQ draft.', 'ultimate-faqs' ) ) );
			}

			$result = $this->suppress_draft( $post, 'rejected' );
			if ( ! is_wp_error( $result ) && ! wp_delete_post( $post_id, true ) ) {
				$result = new WP_Error( 'delete_failed', __( 'Suppression was saved, but the draft could not be deleted.', 'ultimate-faqs' ) ); }
			$this->redirect_result( $result );
		}

		private function authorize( $nonce_action ) {

			global $ewd_ufaq_controller;

			$type = get_post_type_object( EWD_UFAQ_FAQ_POST_TYPE );
			if ( ! $type || ! current_user_can( $ewd_ufaq_controller->settings->get_setting( 'access-role' ) ) || ! current_user_can( $type->cap->edit_posts ) ) {
				wp_die( esc_html__( 'You do not have permission to manage content gaps.', 'ultimate-faqs' ) ); }
			check_admin_referer( $nonce_action );
		}

		private function redirect_result( $result ) {

			$fallback      = admin_url( 'edit.php?post_type=ufaq&page=ewd-ufaq-analytics-privacy' );
			$redirect_to   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
			$referer       = wp_get_referer();
			$requested_url = $redirect_to ? $redirect_to : $referer;
			$url           = wp_validate_redirect( $requested_url, $fallback );
			$url           = add_query_arg( is_wp_error( $result ) ? array( 'ewd_ufaq_gap_error' => $result->get_error_code() ) : array( 'ewd_ufaq_gap_updated' => '1' ), $url );
			wp_safe_redirect( $url );
			exit;
		}

		private static function option_name( $key ) {

			$nibble = preg_match( '/^[a-f0-9]/', (string) $key ) ? substr( $key, 0, 1 ) : '0';

			return 'ewd-ufaq-ai-suppressions-v1-0' . $nibble;
		}

		private static function compare_and_swap( $name, $current, $updated ) {

			global $wpdb;

			$changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					maybe_serialize( $updated ),
					$name,
					maybe_serialize( $current )
				)
			);

			self::invalidate_option_cache( $name );

			return 1 === $changed;
		}

		private static function add_exact( $name, $value ) {

			global $wpdb;

			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
					$name,
					maybe_serialize( $value ),
					'no'
				)
			);

			self::invalidate_option_cache( $name );

			return 1 === $inserted;
		}

		private static function committed_contains( $name, $key ) {

			global $wpdb;

			$value   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$name
				)
			);
			$records = maybe_unserialize( $value );

			return is_array( $records ) && isset( $records[ $key ] );
		}

		private static function invalidate_option_cache( $name ) {
			$alloptions = wp_cache_get( 'alloptions', 'options' );
			if ( is_array( $alloptions ) && array_key_exists( $name, $alloptions ) ) {
				wp_cache_delete( 'alloptions', 'options' );
			}

			$notoptions = wp_cache_get( 'notoptions', 'options' );

			if ( is_array( $notoptions ) && isset( $notoptions[ $name ] ) ) {
				unset( $notoptions[ $name ] );
				wp_cache_set( 'notoptions', $notoptions, 'options' );
			}

			wp_cache_delete( $name, 'options' );
		}

		private static function invalidate_gap_cache() {

			delete_transient( 'ewd_ufaq_content_gaps_v1' );
		}
	}
}
