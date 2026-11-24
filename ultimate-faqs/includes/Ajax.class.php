<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ewdufaqAJAX' ) ) {
	/**
	 * Class to handle AJAX interactions for Ultimate FAQs
	 *
	 * @since 2.0.0
	 */
	class ewdufaqAJAX {

		public function __construct( $register = true ) {
			if ( ! $register ) { return; }


			add_action( 'wp_ajax_ewd_ufaq_reset_saved_search_terms', array( $this, 'reset_saved_search_term' ) );

			add_action( 'wp_ajax_ewd_ufaq_record_view', array( $this, 'record_view' ) );
			add_action( 'wp_ajax_nopriv_ewd_ufaq_record_view', array( $this, 'record_view' ) );


		}

		/**
		 * Get the results of the FAQ search
		 * @since 2.0.0
		 */
		public function return_search_results() {
			if ( class_exists( 'ewdufaqEWDPHSearch', false ) ) { return ( new ewdufaqEWDPHSearch( false ) )->return_search_results(); }
			wp_send_json_error( array( 'code' => 'premium_unavailable' ), 403 );
		}

		/**
		 * Records the number of time an FAQ post is opened
		 * @since 2.0.0
		 */
		public function record_view() {
			global $wpdb;
			if ( !check_ajax_referer( 'ewd-ufaq-js', 'nonce' ) ) {
				ewdufaqHelper::bad_nonce_ajax();
			}

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

			if ( ! $this->is_public_faq( $post_id ) ) { wp_send_json_error( array( 'code' => 'invalid_faq' ), 400 ); }
			if ( ! $this->allow_public_event( 'open', $post_id, 5 * MINUTE_IN_SECONDS ) ) { wp_send_json_success( array( 'duplicate' => true ) ); }

			$this->increment_post_meta( $post_id, 'ufaq_view_count', 1 );
			do_action( 'ewd_ufaq_public_faq_opened', $post_id );

			wp_send_json_success();
		}

		/**
		 * Update the up or down rating for an FAQ
		 * @since 2.0.0
		 */
		public function update_rating() {
			if ( class_exists( 'ewdufaqEWDPHPaidAjax', false ) ) { return ewdufaqEWDPHPaidAjax::update_rating( $this ); }
			wp_send_json_error( array( 'code' => 'premium_unavailable' ), 403 );
		}

		/**
		 * Updates the order of the FAQ, based on the ordering table
		 * @since 2.0.0
		 */
		public function update_faq_order() {
			if ( class_exists( 'ewdufaqEWDPHOrdering', false ) ) { return ewdufaqEWDPHOrdering::save_faq(); }
			wp_send_json_error( array( 'code' => 'premium_unavailable' ), 403 );
		}

		/**
		 * Save the user-set custom order for the category taxonomy terms
		 * @since 2.4.0
		 */
		public function update_category_order() {
			if ( class_exists( 'ewdufaqEWDPHOrdering', false ) ) { return ewdufaqEWDPHOrdering::save_category(); }
			wp_send_json_error( array( 'code' => 'premium_unavailable' ), 403 );
		}

		/**
		 * Records search terms, if enabled
		 * @since 2.2.6
		 */
		public function save_search_term() {
			if ( class_exists( 'ewdufaqEWDPHSearch', false ) ) { return ( new ewdufaqEWDPHSearch( false ) )->save_search_term(); }
			wp_send_json_error( array( 'code' => 'premium_unavailable' ), 403 );
		}

		/**
		 * Resets the saved search terms
		 * @since 2.2.6
		 */
		public function reset_saved_search_term() {
			global $ewd_ufaq_controller;

			if ( !check_ajax_referer( 'ewd-ufaq-admin-js', 'nonce' ) ) {
				ewdufaqHelper::bad_nonce_ajax();
			}

			if ( ! current_user_can( $ewd_ufaq_controller->settings->get_setting( 'access-role' ) ) ) {

				ewdufaqHelper::admin_nopriv_ajax();
			}
			// Administrator deletion remains available even when paid recording is suspended.

  			update_option( 'ewd-ufaq-search-data', array() );

			die();
		}

		private function is_public_faq( $post_id ) {

			$post = get_post( $post_id );

			return $post && EWD_UFAQ_FAQ_POST_TYPE === $post->post_type && 'publish' === $post->post_status;
		}

		private function allow_public_event( $event, $post_id, $ttl ) {

			$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$key = 'ewd_ufaq_evt_' . substr( hash_hmac( 'sha256', $event . '|' . $post_id . '|' . $address . '|' . $user_agent, wp_salt( 'nonce' ) ), 0, 32 );

			if ( get_transient( $key ) ) { return false; }

			return set_transient( $key, 1, max( MINUTE_IN_SECONDS, absint( $ttl ) ) );
		}

		private function increment_post_meta( $post_id, $meta_key, $amount ) {

			global $wpdb;

			add_post_meta( $post_id, $meta_key, 0, true );

			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS SIGNED) + %d WHERE post_id = %d AND meta_key = %s",
					intval( $amount ),
					absint( $post_id ),
					$meta_key
				)
			);
		}
	}
}
