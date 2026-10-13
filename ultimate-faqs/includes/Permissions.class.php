<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ewdufaqPermissions' ) ) {
	/**
	 * Class to handle plugin permissions for Ultimate FAQs
	 *
	 * @since 2.0.0
	 */
	class ewdufaqPermissions {

		private $plugin_permissions;
		private $permission_level;

		public function __construct() {

			$this->plugin_permissions = array(
				'styling'      => 2,
				'premium'      => 2,
				'fields'       => 2,
				'search'       => 2,
				'woocommerce'  => 2,
				'submit-faq'   => 2,
				'ordering'     => 2,
				'labelling'    => 2,
				'import'       => 2,
				'export'       => 2,
				'analytics'    => 2,
				'content-gaps' => 2,
				'ai-lifecycle' => 2,
			);
		}

		public function set_permissions() {

			if ( is_array( get_option( 'ewd-ufaq-permission-level' ) ) ) {
				return; }

			if ( ! empty( get_option( 'ewd-ufaq-permission-level' ) ) ) {

				update_option( 'ewd-ufaq-permission-level', array( get_option( 'ewd-ufaq-permission-level' ) ) );

				return;
			}

			$this->permission_level = 'Yes' === get_option( 'EWD_UFAQ_Full_Version' ) ? 2 : 1;

			update_option( 'ewd-ufaq-permission-level', array( $this->permission_level ) );
		}

		public function get_permission_level() {

			if ( ! is_array( get_option( 'ewd-ufaq-permission-level' ) ) ) {
				$this->set_permissions(); }

			$permissions_array = get_option( 'ewd-ufaq-permission-level' );

			$this->permission_level = is_array( $permissions_array ) ? reset( $permissions_array ) : $permissions_array;
		}

		public function check_permission( $permission_type = '' ) {
			if ( ! array_key_exists( $permission_type, $this->plugin_permissions ) ) {
				return false;
			}
			$contract = $this->get_entitlement();
			return in_array( $permission_type, $contract['capabilities'], true );
		}

		/** Consume only a compatible Helper contract; never interpret licensing responses. */
		public function get_entitlement() {
			$denied = array(
				'effective_status' => 'missing',
				'capabilities'     => array(),
				'reason_code'      => 'helper_unavailable',
			);
			if ( ! defined( 'EWDPH_VERSION' ) || ! class_exists( 'ewdphUFAQCompatibility' ) ) {
				return $denied;
			}
			$compatible = ewdufaqHelperCompatibility::evaluate( true, true, ewdphUFAQCompatibility::get_descriptor(), false );
			if ( 'compatible_premium_inactive' !== $compatible['state'] ) {
				$denied['reason_code'] = 'helper_incompatible';
				return $denied;
			}
			$contract = apply_filters( 'ewd_ufaq_entitlement_contract', null );
			if ( ! is_array( $contract ) || ! isset( $contract['contract_version'], $contract['helper_api_version'], $contract['effective_status'], $contract['capabilities'] )
			|| EWD_UFAQ_ENTITLEMENT_CONTRACT_VERSION !== $contract['contract_version']
			|| EWD_UFAQ_HELPER_API_VERSION !== $contract['helper_api_version']
			|| ! is_array( $contract['capabilities'] ) ) {
				$denied['reason_code'] = 'unsupported_entitlement_contract';
				return $denied;
			}
			if ( ! in_array( $contract['effective_status'], array( 'valid', 'valid_cached', 'trial', 'legacy_transition' ), true ) ) {
				$contract['capabilities'] = array();
			}
			$contract['capabilities'] = array_values( array_intersect( array_keys( $this->plugin_permissions ), $contract['capabilities'] ) );
			return $contract;
		}


		/** Source-verified paid settings: suppress execution, retain stored configuration. */
		public static function setting_permission( $setting ) {
			if ( 0 === strpos( $setting, 'styling-' ) ) {
				return 'styling'; }
			if ( 0 === strpos( $setting, 'label-' ) ) {
				return 'labelling'; }
			$groups = array(
				'premium'     => array( 'display-style', 'number-of-columns', 'responsive-columns', 'category-tabs', 'reveal-effect', 'faqs-per-page', 'page-type', 'faq-ratings', 'thumbs-up-image', 'thumbs-down-image', 'pretty-permalinks', 'social-media', 'compact-faq-meta', 'wpforms-integration', 'wpforms-post-count', 'wpforms-faq-location', 'wpforms-minimum-characters', 'faq-elements-order' ),
				'woocommerce' => array( 'woocommerce-faqs', 'woocommerce-faqs-location', 'woocommerce-use-product' ),
				'search'      => array( 'auto-complete-titles', 'highlight-search-term', 'save-search-terms', 'saved-search-terms' ),
				'submit-faq'  => array( 'allow-proposed-answer', 'submit-faq-author-email', 'submit-faq-updates', 'submit-question-captcha', 'submit-custom-fields', 'submit-required-fields', 'submitted-default-category', 'admin-question-notification', 'admin-notification-email', 'submit-faq-email' ),
				'fields'      => array( 'hide-blank-fields', 'faq-fields' ),
			);
			foreach ( $groups as $capability => $settings ) {
				if ( in_array( $setting, $settings, true ) ) {
					return $capability; }
			}
			return '';
		}

		/** Fixed structural URLs remain intact; custom manual ordering requires its own right. */
		public function effective_setting( $setting, $value, $fallback = null ) {
			$capability = self::setting_permission( $setting );
			if ( 'faq-order-by' === $setting && 'set_order' === $value ) {
				$capability = 'ordering'; }
			return '' !== $capability && ! $this->check_permission( $capability ) ? $fallback : $value;
		}

		public function update_permissions() {

			$this->permission_level = get_option( 'ewd-ufaq-permission-level' );
		}
	}

}
