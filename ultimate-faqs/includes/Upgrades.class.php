<?php
/**
 * Sequential, idempotent data upgrades for Ultimate FAQs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ewdufaqUpgrades' ) ) {
	class ewdufaqUpgrades {

		const LATEST_DATA_VERSION = 1;

		public function __construct() {

			add_action( 'plugins_loaded', array( $this, 'run' ), 1000 );
		}

		/**
		 * Run every missing upgrade in sequence.
		 *
		 * @return bool Whether the site reached the latest data version.
		 */
		public function run() {

			$current_version = absint( get_option( 'ewd-ufaq-data-version', 0 ) );

			try {

				while ( $current_version < self::LATEST_DATA_VERSION ) {

					$next_version = $current_version + 1;
					$method       = 'upgrade_to_' . $next_version;

					if ( ! method_exists( $this, $method ) || ! $this->{$method}() ) {
						return false; }

					if ( ! $this->update_nonautoload_option( 'ewd-ufaq-data-version', $next_version ) ) {
						return false; }

					$current_version = $next_version;
				}
			} catch ( Throwable $exception ) {

				do_action( 'ewd_ufaq_upgrade_failed', $current_version + 1, $exception );

				return false;
			}

			return true;
		}

		/**
		 * Establish inert defaults and compatibility state for Modern Managed FAQs.
		 */
		private function upgrade_to_1() {

			global $ewd_ufaq_controller;

			$settings = get_option( 'ewd-ufaq-settings', array() );
			$settings = is_array( $settings ) ? $settings : array();

			$defaults = array(
				'analytics-enabled'              => false,
				'analytics-retention-days'       => 30,
				'ai-gap-auto-generation-enabled' => false,
				'ai-gap-source-existing-faqs'    => true,
				'ai-gap-source-display-context'  => true,
				'ai-gap-source-selected-content' => '',
				'ai-gap-admin-notes'             => '',
			);

			foreach ( $defaults as $key => $value ) {

				if ( ! array_key_exists( $key, $settings ) ) {
					$settings[ $key ] = $value; }
			}

			update_option( 'ewd-ufaq-settings', $settings );

			if ( get_option( 'ewd-ufaq-settings', array() ) !== $settings ) {
				return false; }

			// No buckets are created here. Inert defaults must not leave scheduled work behind.
			if ( empty( $settings['analytics-enabled'] ) ) {

				wp_clear_scheduled_hook( 'ewd_ufaq_daily_analytics_cleanup' );
				wp_clear_scheduled_hook( 'ewd_ufaq_daily_content_gap_processing' );
			}

			$compatibility_state = is_object( $ewd_ufaq_controller )
			&& is_object( $ewd_ufaq_controller->helper_compatibility )
			? $ewd_ufaq_controller->helper_compatibility->get_state()
			: 'unknown';

			$compatibility_record = array(
				'free_api_version' => EWD_UFAQ_HELPER_API_VERSION,
				'state'            => $compatibility_state,
			);

			return $this->update_nonautoload_option( 'ewd-ufaq-helper-compatibility-state', $compatibility_record );
		}

		private function update_nonautoload_option( $option_name, $value ) {

			update_option( $option_name, $value, false );

			return get_option( $option_name, null ) === $value;
		}
	}
}
