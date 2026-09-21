<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ewdufaqAJAX' ) ) {
	/**
	 * Class to handle AJAX interactions for Ultimate FAQs
	 *
	 * @since 2.0.0
	 */
	class ewdufaqAJAX {

		public function __construct() { 

			add_action( 'wp_ajax_ewd_ufaq_search', array( $this, 'return_search_results' ) );
			add_action( 'wp_ajax_nopriv_ewd_ufaq_search', array( $this, 'return_search_results' ) );

			add_action( 'wp_ajax_ewd_ufaq_record_search_term', array( $this, 'save_search_term' ) );
			add_action( 'wp_ajax_nopriv_ewd_ufaq_record_search_term', array( $this, 'save_search_term' ) );

			add_action( 'wp_ajax_ewd_ufaq_reset_saved_search_terms', array( $this, 'reset_saved_search_term' ) );

			add_action( 'wp_ajax_ewd_ufaq_record_view', array( $this, 'record_view' ) );
			add_action( 'wp_ajax_nopriv_ewd_ufaq_record_view', array( $this, 'record_view' ) );

			add_action( 'wp_ajax_ewd_ufaq_update_rating', array( $this, 'update_rating' ) );
			add_action( 'wp_ajax_nopriv_ewd_ufaq_update_rating', array( $this, 'update_rating' ) );

			add_action( 'wp_ajax_ewd_ufaq_update_faq_order', array( $this, 'update_faq_order' ) );

			add_action( 'wp_ajax_ewd_ufaq_update_category_order', array( $this, 'update_category_order' ) );
		}

		/**
		 * Get the results of the FAQ search
		 * @since 2.0.0
		 */
		public function return_search_results() {
			global $ewd_ufaq_controller;
			if ( !check_ajax_referer( 'ewd-ufaq-js', 'nonce' ) ) {
				ewdufaqHelper::bad_nonce_ajax();
			}

			if ( $ewd_ufaq_controller->helper_compatibility->get_state() !== 'compatible'
				|| ! $ewd_ufaq_controller->permissions->check_permission( 'search' ) ) {

				wp_send_json_error( array( 'code' => 'advanced_search_unavailable' ), 403 );
			}
			$faq_atts = array(
				'is_search'					=> 1,
				'search_string' 			=> '',
				'post__in' 					=> '',
				'post__in_string' 			=> '',
				'include_tag'				=> '',
				'include_category' 			=> isset( $_POST['include_category'] ) ? sanitize_text_field( $_POST['include_category'] ) : '', //so that only the matching category is displayed
				'exclude_category' 			=> '',
				'include_category_ids' 		=> '',
				'exclude_category_ids' 		=> '',
				'include_category_children'	=> '',
				'no_comments' 				=> '',
				'orderby' 					=> '',
				'order' 					=> '',
				'display_all_answers' 		=> '',
				'faq_page' 					=> 1,
				'post_count' 				=> -1
			);

			$query = new ewdufaqQuery( $faq_atts );

			$query->parse_request_args();
			$query->prepare_args();

			$faq_atts['search_string'] = isset( $_REQUEST['search_string'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_string'] ) ) : '';
			$faqs = new ewdufaqViewFAQs( $faq_atts );

			$faqs->set_request_parameters();

			$faqs->set_faqs( $query->get_faqs() );

			$faqs->set_faqs_options();

			$faqs->create_faq_data();

			$faqs->set_faq_properties();

			ob_start();

			$ewd_ufaq_controller->shortcode_printing = true;

			$faqs->print_faqs();

			$ewd_ufaq_controller->shortcode_printing = false;

			$output = ob_get_clean();

			$pagination = '';
			if('yes' != $faqs->show_on_load && 2 > $faqs->faq_page) {
				ob_start();
				$faqs->maybe_print_pagination();
				$pagination = ob_get_clean();
			}

			$output = $output ? $output : '<div class="ewd-ufaq-no-results-found">' . sprintf( $ewd_ufaq_controller->settings->get_setting( 'label-no-results-found' ), $query->args['search_string'] ) . '</div>';

			do_action( 'ewd_ufaq_advanced_search_completed', $query->args['search_string'], count( $faqs->displayed_faqs ), $faq_atts );
			$analytics = apply_filters( 'ewd_ufaq_advanced_search_analytics', array(), $query->args['search_string'], count( $faqs->displayed_faqs ), $faq_atts );
			wp_send_json_success(
				array(
					'output' 		=> $output,
					'pagination' 	=> $pagination,
					'faq_count' 	=> $faqs->faq_count,
					'max_page'		=> $faqs->max_page,
					'request_count'	=> intval( $_POST['request_count'] ),
					'analytics'		=> $analytics,
				)
			);

		    die();
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

			if ( !check_ajax_referer( 'ewd-ufaq-js', 'nonce' ) ) {
				ewdufaqHelper::bad_nonce_ajax();
			}

			$faq_id = isset( $_POST['faq_id'] ) ? absint( $_POST['faq_id'] ) : 0;
			$vote_type = isset( $_POST['vote_type'] ) ? sanitize_key( wp_unslash( $_POST['vote_type'] ) ) : '';

			if ( ! $this->is_public_faq( $faq_id ) || ! in_array( $vote_type, array( 'up', 'down' ), true ) ) {

				wp_send_json_error( array( 'code' => 'invalid_rating' ), 400 );
			}

			if ( ! $this->allow_public_event( 'rating', $faq_id, DAY_IN_SECONDS ) ) { wp_send_json_success( array( 'duplicate' => true ) ); }

			if ( 'up' === $vote_type ) {

				$this->increment_post_meta( $faq_id, 'FAQ_Up_Votes', 1 );
				$this->increment_post_meta( $faq_id, 'FAQ_Total_Score', 1 );
			}
			else {

				$this->increment_post_meta( $faq_id, 'FAQ_Down_Votes', 1 );
				$this->increment_post_meta( $faq_id, 'FAQ_Total_Score', -1 );
			}

			do_action( 'ewd_ufaq_public_faq_helpfulness', $faq_id, $vote_type );

			wp_send_json_success();
		}

		/**
		 * Updates the order of the FAQ, based on the ordering table
		 * @since 2.0.0
		 */
		public function update_faq_order() {
			global $ewd_ufaq_controller;

			if (
				! check_ajax_referer( 'ewd-ufaq-admin-js', 'nonce' ) 
				|| 
				! current_user_can( $ewd_ufaq_controller->settings->get_setting( 'access-role' ) )
			) {
				ewdufaqHelper::admin_nopriv_ajax();
			}

			if ( ! is_array( $_POST['ewd-ufaq-item'] ) or ! $ewd_ufaq_controller->permissions->check_permission( 'ordering' ) ) { return; }

    		foreach ( $_POST['ewd-ufaq-item'] as $key => $id ) {

    		    update_post_meta( intval( $id ), 'ufaq_order', intval( $key ) + 1 );
    		}

   			die();
		}

		/**
		 * Save the user-set custom order for the category taxonomy terms
		 * @since 2.4.0
		 */
		public function update_category_order() {
			global $ewd_ufaq_controller;

			// Authenticate request
			if (
				! check_ajax_referer( 'ewd-ufaq-admin-js', 'nonce' ) 
				|| 
				! current_user_can( $ewd_ufaq_controller->settings->get_setting( 'access-role' ) )
			) {
				ewdufaqHelper::admin_nopriv_ajax();
			}

			$ids = is_array( $_POST['tag'] ) ? array_map( 'intval', $_POST['tag'] ) : array();
		
			foreach ( $ids as $order => $term_id ) {

				update_term_meta( $term_id, 'ufaq_category_order', $order );
			}
		}

		/**
		 * Records search terms, if enabled
		 * @since 2.2.6
		 */
		public function save_search_term() {
			global $ewd_ufaq_controller;

			if ( !check_ajax_referer( 'ewd-ufaq-js', 'nonce' ) ) {
				ewdufaqHelper::bad_nonce_ajax();
			}

			if ( empty( $ewd_ufaq_controller->settings->get_setting( 'save-search-terms' ) ) ) { return; }

			$search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( wp_unslash( $_POST['search_term'] ) ) : '';
			$search_term = function_exists( 'mb_substr' ) ? mb_substr( $search_term, 0, 200 ) : substr( $search_term, 0, 200 );
  			if ( empty( $search_term ) or strlen( $search_term ) < 2 ) { die(); }

  			$search_terms = get_option( 'ewd-ufaq-search-data', array() );

			if ( isset( $search_terms[ $search_term ] ) ) {

				$search_terms[ $search_term ]++;
			}
			elseif ( count( $search_terms ) < 1000 ) {

				$search_terms[ $search_term ] = 1;
			}
  			update_option( 'ewd-ufaq-search-data', $search_terms );

  			die();
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
			if ( empty( $ewd_ufaq_controller->settings->get_setting( 'save-search-terms' ) ) ) { return; }

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
