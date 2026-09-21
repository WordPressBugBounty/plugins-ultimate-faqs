<?php
/**
 * Common analytics event bridge. Free registers no analytics storage or public route.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ewdufaqAnalyticsBridge' ) ) {
	class ewdufaqAnalyticsBridge {

		private $contexts        = array();
		private $assets_enqueued = false;

		public function __construct() {

			add_action( 'ewd_ufaq_basic_search_rendered', array( $this, 'register_search' ), 10, 5 );
			add_action( 'ewd_ufaq_faq_rendered', array( $this, 'register_faq' ), 10, 2 );
			add_filter( 'ewd_ufaq_advanced_search_analytics', array( $this, 'advanced_search_context' ), 10, 4 );
			add_action( 'wp_footer', array( $this, 'print_contexts' ), 5 );
		}

		public function advanced_search_context( $context, $term, $result_count, $args ) {

			$term = trim( sanitize_text_field( $term ) );
			if ( '' === $term ) {
				return $context; }
			$payload  = array(
				'event'        => 'search',
				'display_hash' => hash( 'sha256', 'advanced|' . wp_json_encode( $args ) ),
				'locale'       => determine_locale(),
				'search_text'  => $term,
				'query_hash'   => hash( 'sha256', determine_locale() . '|' . $term ),
				'result_count' => absint( $result_count ),
			);
			$token    = apply_filters( 'ewd_ufaq_analytics_sign_event_context', '', $payload );
			$endpoint = apply_filters( 'ewd_ufaq_analytics_endpoint', '' );
			if ( ! is_string( $token ) || '' === $token || ! is_string( $endpoint ) || '' === $endpoint ) {
				return $context; }
			return array(
				'event'       => 'search',
				'endpoint'    => $endpoint,
				'token'       => $token,
				'storage_key' => substr( hash( 'sha256', $payload['query_hash'] . '|' . $payload['display_hash'] . '|' . absint( $result_count ) ), 0, 32 ),
			);
		}

		public function register_search( $identity, $term, $result_count, $args, $faq_ids = array() ) {

			$display_hash = hash( 'sha256', $identity . '|' . wp_json_encode( $args ) );
			$this->register_context(
				array(
					'event'        => 'search',
					'display_hash' => $display_hash,
					'locale'       => determine_locale(),
					'search_text'  => $term,
					'query_hash'   => hash( 'sha256', determine_locale() . '|' . $term ),
					'result_count' => absint( $result_count ),
					'faq_ids'      => array_slice( array_values( array_unique( array_map( 'absint', $faq_ids ) ) ), 0, 20 ),
				)
			);
		}

		public function register_faq( $faq_id, $view ) {

			$faq_id = absint( $faq_id );

			if ( ! $faq_id ) {
				return; }

			$display_hash = hash( 'sha256', get_queried_object_id() . '|faq|' . $faq_id );

			foreach ( array( 'open', 'helpful_yes', 'helpful_no' ) as $event ) {

				$this->register_context(
					array(
						'event'        => $event,
						'display_hash' => $display_hash,
						'locale'       => determine_locale(),
						'faq_id'       => $faq_id,
						'search_text'  => ! empty( $view->search_string ) ? $view->search_string : '',
						'query_hash'   => ! empty( $view->search_string ) ? hash( 'sha256', determine_locale() . '|' . $view->search_string ) : '',
					)
				);
			}
		}

		private function register_context( $payload ) {

			if ( count( $this->contexts ) >= 100 ) {
				return; }

			$token    = apply_filters( 'ewd_ufaq_analytics_sign_event_context', '', $payload );
			$endpoint = apply_filters( 'ewd_ufaq_analytics_endpoint', '' );

			if ( ! is_string( $token ) || '' === $token || ! is_string( $endpoint ) || '' === $endpoint ) {
				return; }

			$storage_key = substr( hash( 'sha256', $payload['event'] . '|' . ( isset( $payload['faq_id'] ) ? absint( $payload['faq_id'] ) : 0 ) . '|' . ( isset( $payload['query_hash'] ) ? $payload['query_hash'] : '' ) . '|' . $payload['display_hash'] ), 0, 32 );
			foreach ( $this->contexts as $existing ) {
				if ( $existing['storage_key'] === $storage_key ) {
					return; }
			}

			$this->contexts[] = array(
				'event'       => $payload['event'],
				'faq_id'      => isset( $payload['faq_id'] ) ? absint( $payload['faq_id'] ) : 0,
				'token'       => $token,
				'storage_key' => $storage_key,
			);

			if ( ! $this->assets_enqueued ) {

				wp_enqueue_script( 'ewd-ufaq-analytics', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq-analytics.js', array(), EWD_UFAQ_VERSION, true );
				$this->assets_enqueued = true;
			}
		}

		public function print_contexts() {

			if ( empty( $this->contexts ) ) {
				return; }

			wp_add_inline_script(
				'ewd-ufaq-analytics',
				'window.ewdUfaqAnalytics=' . wp_json_encode(
					array(
						'endpoint' => apply_filters( 'ewd_ufaq_analytics_endpoint', '' ),
						'contexts' => $this->contexts,
					)
				) . ';',
				'before'
			);
		}
	}
}
