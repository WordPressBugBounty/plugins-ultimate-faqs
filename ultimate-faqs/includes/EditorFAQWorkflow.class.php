<?php
/**
 * Authenticated block-editor workflow for reusable FAQ records.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ewdufaqEditorFAQWorkflow' ) ) {
	class ewdufaqEditorFAQWorkflow {

		private $title_search_term = '';

		public function __construct() {

			add_action( 'wp_ajax_ewd_ufaq_editor_search_faqs', array( $this, 'search_faqs' ) );
			add_action( 'wp_ajax_ewd_ufaq_editor_create_faq', array( $this, 'create_faq' ) );
			add_action( 'wp_ajax_ewd_ufaq_editor_discard_created_faq', array( $this, 'discard_created_faq' ) );
			add_action( 'save_post', array( $this, 'note_editor_save' ), 10, 2 );
		}

		public function search_faqs() {

			$page = $this->authorize_editor_request();
			$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
			$term = trim( $term );

			if ( self::text_length( $term ) < 2 || self::text_length( $term ) > 200 ) {

				$this->send_error( 'invalid_term', __( 'Enter between 2 and 200 characters to search FAQ titles.', 'ultimate-faqs' ), 400 );
			}

			$exclude_ids = isset( $_POST['exclude_ids'] ) && is_array( $_POST['exclude_ids'] )
				? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['exclude_ids'] ) ) ) ) )
				: array();

			$exclude_ids    = array_slice( $exclude_ids, 0, 100 );
			$cache_key      = md5(
				wp_json_encode(
					array(
						'user_id'      => get_current_user_id(),
						'locale'       => determine_locale(),
						'term'         => $term,
						'exclude_ids'  => $exclude_ids,
						'posts_change' => wp_cache_get_last_changed( 'posts' ),
					)
				)
			);
			$cached_results = wp_cache_get( $cache_key, 'ewd_ufaq_editor_search', false, $cache_found );

			if ( $cache_found && is_array( $cached_results ) ) {

				wp_send_json_success(
					array(
						'page_post_id' => $page->ID,
						'results'      => $cached_results,
					)
				);
			}

			$this->title_search_term = $term;

			add_filter( 'posts_search', array( $this, 'filter_title_search' ), 20, 2 );
			add_filter( 'posts_orderby', array( $this, 'filter_title_orderby' ), 20, 2 );

			$query = new WP_Query(
				array(
					'post_type'                    => EWD_UFAQ_FAQ_POST_TYPE,
					'post_status'                  => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'post__not_in'                 => $exclude_ids,
					'posts_per_page'               => 10,
					'no_found_rows'                => true,
					'suppress_filters'             => false,
					'ewd_ufaq_editor_title_search' => true,
				)
			);

			remove_filter( 'posts_search', array( $this, 'filter_title_search' ), 20 );
			remove_filter( 'posts_orderby', array( $this, 'filter_title_orderby' ), 20 );

			$results = array();

			foreach ( $query->posts as $faq_post ) {

				$can_read = current_user_can( 'read_post', $faq_post->ID );
				$can_edit = current_user_can( 'edit_post', $faq_post->ID );

				if ( ! $can_read && ! $can_edit ) {
					continue;
				}

				$status_object = get_post_status_object( $faq_post->post_status );

				$results[] = array(
					'id'            => $faq_post->ID,
					'title'         => get_the_title( $faq_post ),
					'status'        => $faq_post->post_status,
					'status_label'  => $status_object ? $status_object->label : $faq_post->post_status,
					'edit_url'      => $can_edit ? get_edit_post_link( $faq_post->ID, 'raw' ) : '',
					'is_selectable' => 'publish' === $faq_post->post_status && $can_read,
				);
			}

			wp_cache_set( $cache_key, $results, 'ewd_ufaq_editor_search', MINUTE_IN_SECONDS );

			wp_send_json_success(
				array(
					'page_post_id' => $page->ID,
					'results'      => $results,
				)
			);
		}

		public function filter_title_search( $search, $query ) {

			global $wpdb;

			if ( ! $query->get( 'ewd_ufaq_editor_title_search' ) ) {
				return $search;
			}

			$like = '%' . $wpdb->esc_like( $this->title_search_term ) . '%';

			return $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $like );
		}

		public function filter_title_orderby( $orderby, $query ) {

			global $wpdb;

			if ( ! $query->get( 'ewd_ufaq_editor_title_search' ) ) {
				return $orderby;
			}

			$prefix = $wpdb->esc_like( $this->title_search_term ) . '%';

			return $wpdb->prepare(
				"CASE WHEN {$wpdb->posts}.post_title = %s THEN 0 WHEN {$wpdb->posts}.post_title LIKE %s THEN 1 ELSE 2 END, {$wpdb->posts}.post_title ASC",
				$this->title_search_term,
				$prefix
			);
		}

		public function create_faq() {

			$page     = $this->authorize_editor_request();
			$faq_type = get_post_type_object( EWD_UFAQ_FAQ_POST_TYPE );

			if ( ! $faq_type || ! current_user_can( $faq_type->cap->publish_posts ) ) {

				$this->send_error( 'cannot_publish_faqs', __( 'You do not have permission to publish FAQs.', 'ultimate-faqs' ), 403 );
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => EWD_UFAQ_FAQ_POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $this->get_question(),
					'post_content' => isset( $_POST['answer'] ) ? wp_kses_post( wp_unslash( $_POST['answer'] ) ) : '',
					'post_author'  => get_current_user_id(),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {

				$this->send_error( 'create_failed', $post_id->get_error_message(), 500 );
			}

			$faq_post = get_post( $post_id );

			if ( ! $faq_post || 'publish' !== $faq_post->post_status ) {

				$this->send_error( 'create_failed', __( 'WordPress did not publish the new FAQ.', 'ultimate-faqs' ), 500 );
			}

			if ( false === update_post_meta( $post_id, 'ufaq_order', 9999 ) ) {

				wp_delete_post( $post_id, true );
				$this->send_error( 'create_failed', __( 'The new FAQ could not be initialized for display.', 'ultimate-faqs' ), 500 );
			}

			// This short-lived receipt authorizes rollback of this creation request only.
			// It is not stored on the FAQ or in the block's saved attributes.
			$token      = wp_generate_password( 32, false, false );
			$save_key   = 'ewd_ufaq_editor_save_' . $page->ID;
			$save_token = get_transient( $save_key );
			if ( ! $save_token ) {
				$save_token = wp_generate_password( 32, false, false );
			}
			set_transient( $save_key, $save_token, DAY_IN_SECONDS );
			$stored = set_transient(
				'ewd_ufaq_discard_' . hash( 'sha256', $token ),
				array(
					'faq_id'     => $post_id,
					'user_id'    => get_current_user_id(),
					'editor_id'  => $page->ID,
					'save_token' => $save_token,
				),
				DAY_IN_SECONDS
			);

			if ( ! $stored ) {
				wp_delete_post( $post_id, true );
				$this->send_error( 'create_failed', __( 'The new FAQ could not be initialized for editing.', 'ultimate-faqs' ), 500 );
			}

			$response                  = $this->get_faq_response( $faq_post );
			$response['discard_token'] = $token;
			wp_send_json_success( $response );
		}

		public function discard_created_faq() {

			$page   = $this->authorize_editor_request();
			$faq_id = isset( $_POST['faq_id'] ) ? absint( $_POST['faq_id'] ) : 0;
			$token  = isset( $_POST['discard_token'] ) ? sanitize_text_field( wp_unslash( $_POST['discard_token'] ) ) : '';

			if ( ! preg_match( '/^[a-zA-Z0-9]{32}$/D', $token ) ) {
				$this->send_error( 'invalid_discard', __( 'This FAQ cannot be removed automatically. Reload the page and try again.', 'ultimate-faqs' ), 403 );
			}

			$key     = 'ewd_ufaq_discard_' . hash( 'sha256', $token );
			$receipt = get_transient( $key );

			if ( ! is_array( $receipt ) ) {
				// An expired receipt cannot authorize deletion, but unlinking is still safe.
				wp_send_json_success(
					array(
						'id'        => $faq_id,
						'discarded' => false,
					)
				);
			}

			if ( $faq_id !== (int) $receipt['faq_id']
				|| get_current_user_id() !== (int) $receipt['user_id']
				|| $page->ID !== (int) $receipt['editor_id'] ) {
				$this->send_error( 'invalid_discard', __( 'This request cannot delete that FAQ.', 'ultimate-faqs' ), 403 );
			}

			$faq = get_post( $faq_id );
			if ( ! $faq ) {
				delete_transient( $key );
				wp_send_json_success(
					array(
						'id'        => $faq_id,
						'discarded' => true,
					)
				);
			}

			if ( EWD_UFAQ_FAQ_POST_TYPE !== $faq->post_type
				|| get_current_user_id() !== (int) $faq->post_author
				|| ! current_user_can( 'delete_post', $faq_id ) ) {
				$this->send_error( 'cannot_delete_faq', __( 'You do not have permission to delete this FAQ.', 'ultimate-faqs' ), 403 );
			}

			// Also protect against a save in another editor tab or reuse on another page.
			$save_token = get_transient( 'ewd_ufaq_editor_save_' . $page->ID );
			if ( ! is_string( $save_token ) || ! hash_equals( $receipt['save_token'], $save_token ) || $this->has_saved_reference( $faq_id, $page->ID ) ) {
				delete_transient( $key );
				wp_send_json_success(
					array(
						'id'        => $faq_id,
						'discarded' => false,
					)
				);
			}

			if ( 'trash' !== $faq->post_status && ! wp_trash_post( $faq_id ) ) {
				$this->send_error( 'discard_failed', __( 'The FAQ could not be moved to Trash. Please try again.', 'ultimate-faqs' ), 500 );
			}

			delete_transient( $key );
			wp_send_json_success(
				array(
					'id'        => $faq_id,
					'discarded' => true,
				)
			);
		}

		public function note_editor_save( $post_id, $post ) {

			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || 'revision' === $post->post_type || EWD_UFAQ_FAQ_POST_TYPE === $post->post_type ) {
				return;
			}

			$key = 'ewd_ufaq_editor_save_' . $post_id;
			if ( get_transient( $key ) ) {
				// Retire rollback receipts only; this never changes an FAQ's status.
				set_transient( $key, wp_generate_password( 32, false, false ), DAY_IN_SECONDS );
			}
		}

		private function has_saved_reference( $faq_id, $editor_id ) {

			global $wpdb;

			// Fail conservatively: a saved occurrence of this numeric ID may be a block,
			// shortcode, reusable pattern, or another plugin's reference. Never delete it.
			$reference = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type <> 'revision' AND post_status NOT IN ('trash', 'auto-draft')
					AND ID <> %d
					AND post_content REGEXP %s LIMIT 1",
					$editor_id,
					'(^|[^0-9])' . $faq_id . '([^0-9]|$)'
				)
			);
			return null !== $reference || '' !== $wpdb->last_error;
		}

		private function authorize_editor_request() {

			if ( ! is_user_logged_in() || ! check_ajax_referer( 'ewd-ufaq-editor', 'nonce', false ) ) {

				$this->send_error( 'invalid_nonce', __( 'Your editor session expired. Refresh the page and try again.', 'ultimate-faqs' ), 403 );
			}

			$page_id  = isset( $_POST['page_post_id'] ) ? absint( $_POST['page_post_id'] ) : 0;
			$page     = get_post( $page_id );
			$faq_type = get_post_type_object( EWD_UFAQ_FAQ_POST_TYPE );

			if ( ! $page || in_array( $page->post_status, array( 'trash', 'inherit' ), true ) ) {

				$this->send_error( 'invalid_page', __( 'The current editor is not available for FAQ management.', 'ultimate-faqs' ), 404 );
			}

			if ( ! current_user_can( 'edit_post', $page->ID )
			|| ! $faq_type
			|| ! current_user_can( $faq_type->cap->edit_posts ) ) {

				$this->send_error( 'forbidden', __( 'You do not have permission to manage FAQs from this editor.', 'ultimate-faqs' ), 403 );
			}

			return $page;
		}

		private function get_question() {

			$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
			$question = trim( $question );

			if ( '' === $question || self::text_length( $question ) > 500 ) {

				$this->send_error( 'invalid_question', __( 'The FAQ question is required and must not exceed 500 characters.', 'ultimate-faqs' ), 400 );
			}

			return $question;
		}

		private function get_faq_response( $faq_post ) {

			return array(
				'id'       => $faq_post->ID,
				'status'   => $faq_post->post_status,
				'title'    => $faq_post->post_title,
				'question' => $faq_post->post_title,
				'edit_url' => get_edit_post_link( $faq_post->ID, 'raw' ),
			);
		}

		private function send_error( $code, $message, $status, $data = array() ) {

			wp_send_json_error(
				array_merge(
					array(
						'code'    => $code,
						'message' => $message,
					),
					$data
				),
				$status
			);
		}

		public static function text_length( $text ) {

			return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		}
	}
}
