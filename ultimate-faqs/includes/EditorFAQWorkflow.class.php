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
			add_action( 'wp_ajax_ewd_ufaq_editor_search_categories', array( $this, 'search_categories' ) );
			add_action( 'wp_ajax_ewd_ufaq_editor_retry_categories', array( $this, 'retry_categories' ) );
			add_action( 'wp_ajax_ewd_ufaq_editor_trash_faq', array( $this, 'trash_faq' ) );
			add_action( 'wp_ajax_ewd_ufaq_editor_discard_created_faq', array( $this, 'discard_created_faq' ) );
			add_action( 'save_post', array( $this, 'note_editor_save' ), 10, 2 );
			add_action( 'rest_api_init', array( $this, 'register_editor_fields' ) );
		}

		public function register_editor_fields() {
			register_rest_field(
				EWD_UFAQ_FAQ_POST_TYPE,
				'ewd_ufaq_can_delete',
				array(
					'get_callback' => static function ( $record ) {
						return current_user_can( 'delete_post', $record['id'] ); },
					'schema'       => array(
						'type'     => 'boolean',
						'context'  => array( 'edit' ),
						'readonly' => true,
					),
				)
			);
		}

		public function search_faqs() {

			$page        = $this->authorize_editor_request();
			$term        = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
			$term        = trim( $term );
			$result_page = min( 10000, max( 1, isset( $_POST['result_page'] ) ? absint( $_POST['result_page'] ) : 1 ) );

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
						'page'         => $result_page,
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
						'results'      => $cached_results['results'],
						'has_more'     => $cached_results['has_more'],
						'result_page'  => $result_page,
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
					'posts_per_page'               => 21,
					'offset'                       => ( $result_page - 1 ) * 20,
					'no_found_rows'                => true,
					'suppress_filters'             => false,
					'ewd_ufaq_editor_title_search' => true,
				)
			);

			remove_filter( 'posts_search', array( $this, 'filter_title_search' ), 20 );
			remove_filter( 'posts_orderby', array( $this, 'filter_title_orderby' ), 20 );

			$results = array();

			foreach ( array_slice( $query->posts, 0, 20 ) as $faq_post ) {

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
					'can_delete'    => current_user_can( 'delete_post', $faq_post->ID ),
					'is_selectable' => 'publish' === $faq_post->post_status && $can_read,
				);
			}

			$has_more = count( $query->posts ) > 20;
			wp_cache_set(
				$cache_key,
				array(
					'results'  => $results,
					'has_more' => $has_more,
				),
				'ewd_ufaq_editor_search',
				MINUTE_IN_SECONDS
			);

			wp_send_json_success(
				array(
					'page_post_id' => $page->ID,
					'results'      => $results,
					'has_more'     => $has_more,
					'result_page'  => $result_page,
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
				"CASE WHEN {$wpdb->posts}.post_title = %s THEN 0 WHEN {$wpdb->posts}.post_title LIKE %s THEN 1 ELSE 2 END, {$wpdb->posts}.post_title ASC, {$wpdb->posts}.ID ASC",
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
			$category_ids = $this->validated_category_ids();

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
			$response                  = array_merge( $response, $this->assign_categories( $post_id, $category_ids ) );
			wp_send_json_success( $response );
		}

		/** Bounded existing terms only; hierarchy labels never hydrate the whole tree. */
		public function search_categories() {
			$this->authorize_editor_request();
			$this->authorize_category_assignment();
			$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
			if ( self::text_length( $term ) > 200 ) {
				$this->send_error( 'invalid_term', __( 'Category searches must not exceed 200 characters.', 'ultimate-faqs' ), 400 );
			}
			$page  = min( 10000, max( 1, isset( $_POST['result_page'] ) ? absint( $_POST['result_page'] ) : 1 ) );
			$limit = min( 50, max( 1, isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20 ) );
			$terms = get_terms(
				array(
					'taxonomy'               => EWD_UFAQ_FAQ_CATEGORY_TAXONOMY,
					'hide_empty'             => false,
					'hierarchical'           => false,
					'orderby'                => 'name',
					'order'                  => 'ASC',
					'search'                 => $term,
					'number'                 => $limit + 1,
					'offset'                 => ( $page - 1 ) * $limit,
					'update_term_meta_cache' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				$this->send_error( 'category_search_failed', __( 'Categories could not be loaded. Please try again.', 'ultimate-faqs' ), 500 );
			}
			$results = array();
			foreach ( array_slice( $terms, 0, $limit ) as $category ) {
				$label     = $category->name;
				$parent_id = (int) $category->parent;
				$seen      = array( $category->term_id );
				while ( $parent_id && count( $seen ) < 12 && ! in_array( $parent_id, $seen, true ) ) {
					$parent = get_term( $parent_id, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
					if ( ! $parent || is_wp_error( $parent ) ) {
						break; }
					$label     = $parent->name . ' / ' . $label;
					$seen[]    = $parent_id;
					$parent_id = (int) $parent->parent;
				}
				$results[] = array(
					'id'    => $category->term_id,
					'name'  => $category->name,
					'label' => $label,
					'slug'  => $category->slug,
				);
			}
			wp_send_json_success(
				array(
					'results'     => $results,
					'result_page' => $page,
					'has_more'    => count( $terms ) > $limit,
				)
			);
		}

		private function authorize_category_assignment() {
			$taxonomy = get_taxonomy( EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
			if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
				$this->send_error( 'cannot_assign_categories', __( 'You do not have permission to assign FAQ categories.', 'ultimate-faqs' ), 403 );
			}
		}

		private function validated_category_ids() {
			$values = isset( $_POST['category_ids'] ) ? wp_unslash( $_POST['category_ids'] ) : array();
			if ( ! is_array( $values ) || count( $values ) > 50 ) {
				$this->send_error( 'invalid_categories', __( 'Select no more than 50 existing FAQ categories.', 'ultimate-faqs' ), 400 );
			}
			if ( $values ) {
				$this->authorize_category_assignment(); }
			$ids = array();
			foreach ( $values as $value ) {
				if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || ! absint( $value ) ) {
					$this->send_error( 'invalid_categories', __( 'One of the selected FAQ categories is invalid. Refresh the categories and try again.', 'ultimate-faqs' ), 400 );
				}
				$id       = absint( $value );
				$category = get_term( $id, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
				if ( ! $category || is_wp_error( $category ) ) {
					$this->send_error( 'invalid_categories', __( 'One of the selected FAQ categories no longer exists. Refresh the categories and try again.', 'ultimate-faqs' ), 400 );
				}
				$ids[] = $id;
			}
			return array_values( array_unique( $ids ) );
		}

		/** A partial failure is still a successful creation, never an invitation to re-insert. */
		private function assign_categories( $faq_id, $ids ) {
			$taxonomy   = get_taxonomy( EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
			$can_assign = $taxonomy && current_user_can( $taxonomy->cap->assign_terms );
			$result     = $ids && $can_assign ? wp_set_object_terms( $faq_id, $ids, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY, false ) : array();
			$persisted  = wp_get_object_terms( $faq_id, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY, array( 'fields' => 'ids' ) );
			$expected   = $ids;
			$actual     = is_wp_error( $persisted ) ? array() : array_map( 'intval', $persisted );
			$names      = array();
			foreach ( $actual as $category_id ) {
				$category = get_term( $category_id, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
				if ( $category && ! is_wp_error( $category ) ) {
					$names[] = $category->name; }
			}
			sort( $expected );
			sort( $actual );
			if ( is_wp_error( $result ) || is_wp_error( $persisted ) || $expected !== $actual ) {
				return array(
					'partial_failure' => true,
					'category_ids'    => $actual,
					'category_names'  => $names,
					'category_error'  => __( 'FAQ created, but its categories were not assigned. Retry category assignment or use Edit FAQ.', 'ultimate-faqs' ),
				);
			}
			return array(
				'partial_failure' => false,
				'category_ids'    => $actual,
				'category_names'  => $names,
			);
		}

		/** Retry is scoped to the original creation receipt, not arbitrary existing FAQs. */
		public function retry_categories() {
			$page    = $this->authorize_editor_request();
			$faq_id  = isset( $_POST['faq_id'] ) ? absint( $_POST['faq_id'] ) : 0;
			$token   = isset( $_POST['discard_token'] ) ? sanitize_text_field( wp_unslash( $_POST['discard_token'] ) ) : '';
			$receipt = preg_match( '/^[a-zA-Z0-9]{32}$/D', $token ) ? get_transient( 'ewd_ufaq_discard_' . hash( 'sha256', $token ) ) : false;
			$faq     = get_post( $faq_id );
			if ( ! is_array( $receipt ) || $faq_id !== (int) $receipt['faq_id'] || $page->ID !== (int) $receipt['editor_id']
				|| get_current_user_id() !== (int) $receipt['user_id'] || ! $faq || EWD_UFAQ_FAQ_POST_TYPE !== $faq->post_type
				|| 'trash' === $faq->post_status || ! current_user_can( 'edit_post', $faq_id ) ) {
				$this->send_error( 'cannot_retry_categories', __( 'Categories cannot be retried from this session. Use Edit FAQ instead.', 'ultimate-faqs' ), 403 );
			}
			$ids = $this->validated_category_ids();
			$this->authorize_category_assignment();
			wp_send_json_success( array_merge( array( 'id' => $faq_id ), $this->assign_categories( $faq_id, $ids ) ) );
		}

		/** Explicit reversible Trash never changes page/block references. */
		public function trash_faq() {
			$this->authorize_editor_request();
			$faq_id = isset( $_POST['faq_id'] ) && is_scalar( $_POST['faq_id'] ) && ctype_digit( (string) $_POST['faq_id'] ) ? absint( $_POST['faq_id'] ) : 0;
			if ( ! $faq_id ) {
				$this->send_error( 'invalid_faq', __( 'Select a valid FAQ.', 'ultimate-faqs' ), 400 );
			}
			$faq = get_post( $faq_id );
			if ( ! $faq ) {
				wp_send_json_success(
					array(
						'id'     => $faq_id,
						'status' => 'missing',
					)
				); }
			if ( EWD_UFAQ_FAQ_POST_TYPE !== $faq->post_type || ! current_user_can( 'delete_post', $faq_id ) ) {
				$this->send_error( 'cannot_trash_faq', __( 'You do not have permission to move this FAQ to Trash.', 'ultimate-faqs' ), 403 );
			}
			if ( 'trash' !== $faq->post_status ) {
				if ( ! self::trash_enabled() ) {
					$this->send_error( 'trash_disabled', __( 'Trash is disabled by site policy. Manage this FAQ from the central FAQs screen; it has not been deleted.', 'ultimate-faqs' ), 409 );
				}
				if ( ! wp_trash_post( $faq_id ) || 'trash' !== get_post_status( $faq_id ) ) {
					$this->send_error( 'trash_failed', __( 'The FAQ could not be moved to Trash. Please try again.', 'ultimate-faqs' ), 500 );
				}
			}
			// Retire only this user's applicable creation receipt, if supplied.
			$token = isset( $_POST['discard_token'] ) ? sanitize_text_field( wp_unslash( $_POST['discard_token'] ) ) : '';
			if ( preg_match( '/^[a-zA-Z0-9]{32}$/D', $token ) ) {
				$key     = 'ewd_ufaq_discard_' . hash( 'sha256', $token );
				$receipt = get_transient( $key );
				if ( is_array( $receipt ) && $faq_id === (int) $receipt['faq_id'] && get_current_user_id() === (int) $receipt['user_id'] ) {
					delete_transient( $key );
				}
			}
			wp_send_json_success(
				array(
					'id'     => $faq_id,
					'status' => 'trash',
				)
			);
		}

		public static function trash_enabled() {
			return defined( 'EMPTY_TRASH_DAYS' ) && (int) EMPTY_TRASH_DAYS > 0;
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

			if ( 'trash' !== $faq->post_status && ! self::trash_enabled() ) {
				// Removing a placement must never become a permanent-delete operation.
				delete_transient( $key );
				wp_send_json_success(
					array(
						'id'        => $faq_id,
						'discarded' => false,
					)
				);
			}
			if ( 'trash' !== $faq->post_status && ( ! wp_trash_post( $faq_id ) || 'trash' !== get_post_status( $faq_id ) ) ) {
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
				'id'         => $faq_post->ID,
				'status'     => $faq_post->post_status,
				'title'      => $faq_post->post_title,
				'question'   => $faq_post->post_title,
				'edit_url'   => get_edit_post_link( $faq_post->ID, 'raw' ),
				'can_delete' => current_user_can( 'delete_post', $faq_post->ID ),
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
