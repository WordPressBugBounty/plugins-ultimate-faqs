<?php
/**
 * Free, page-load FAQ search. Query scope is reconstructed from trusted display attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ewdufaqBasicSearch' ) ) {
	class ewdufaqBasicSearch {

		const PAGE_SIZE = 20;

		private static $occurrences = array();

		public static function render( $args ) {

			$identity  = self::get_identity( $args );
			$term      = self::get_submitted_term( $identity );
			$submitted = null !== $term;
			$page_key  = 'ufaq_search_page_' . substr( hash( 'sha256', $identity ), 0, 10 );
			$page      = $submitted && isset( $_GET[ $page_key ] ) ? max( 1, absint( $_GET[ $page_key ] ) ) : 1;
			$posts     = array();
			$has_next  = false;

			if ( ( $submitted && '' !== $term ) || ! empty( $args['show_on_load'] ) ) {

				$query_result = self::query( $args, null === $term ? '' : $term, $page );
				$posts        = $query_result['posts'];
				$has_next     = $query_result['has_next'];
			}

			wp_enqueue_script( 'ewd-ufaq-basic-search', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq-basic-search.js', array(), EWD_UFAQ_VERSION, true );

			$input_id   = 'ewd-ufaq-search-input-' . esc_attr( substr( hash( 'sha256', $identity ), 0, 12 ) );
			$results_id = 'ewd-ufaq-search-results-' . esc_attr( substr( hash( 'sha256', $identity ), 0, 12 ) );
			$action     = self::get_form_action();

			ob_start();
			?>
		<div class="ewd-ufaq-basic-search" data-ewd-ufaq-search-submitted="<?php echo $submitted ? 'true' : 'false'; ?>">
			<form method="get" action="<?php echo esc_url( $action ); ?>" role="search" aria-controls="<?php echo esc_attr( $results_id ); ?>">
				<label class="ewd-ufaq-field-label" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html__( 'Enter your question', 'ultimate-faqs' ); ?></label>
				<input id="<?php echo esc_attr( $input_id ); ?>" name="ufaq_search_term" type="search" maxlength="200" value="<?php echo esc_attr( null === $term ? '' : $term ); ?>" />
				<input name="ufaq_search_id" type="hidden" value="<?php echo esc_attr( $identity ); ?>" />
				<?php self::print_preserved_query_fields(); ?>
				<button type="submit"><?php echo esc_html__( 'Search', 'ultimate-faqs' ); ?></button>
			</form>
			<div id="<?php echo esc_attr( $results_id ); ?>" class="ewd-ufaq-basic-search-results" tabindex="-1" aria-live="polite">
				<?php
				if ( $submitted && empty( $posts ) ) {
					/* translators: %s: FAQ search term. */
					printf( '<p class="ewd-ufaq-no-results">%s</p>', esc_html( sprintf( __( 'No FAQs contained the term “%s”.', 'ultimate-faqs' ), $term ) ) );
				} elseif ( ! empty( $posts ) ) {
					echo self::render_posts( $posts, $args, $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
				<?php if ( $submitted && ( $page > 1 || $has_next ) ) : ?>
				<nav class="ewd-ufaq-basic-search-pagination" aria-label="<?php echo esc_attr__( 'FAQ search result pages', 'ultimate-faqs' ); ?>">
					<?php
					if ( $page > 1 ) :
						?>
						<a href="<?php echo esc_url( self::page_url( $action, $identity, $term, $page_key, $page - 1 ) ); ?>"><?php echo esc_html__( 'Previous', 'ultimate-faqs' ); ?></a><?php endif; ?>
					<?php
					if ( $has_next ) :
						?>
						<a href="<?php echo esc_url( self::page_url( $action, $identity, $term, $page_key, $page + 1 ) ); ?>"><?php echo esc_html__( 'Next', 'ultimate-faqs' ); ?></a><?php endif; ?>
				</nav>
			<?php endif; ?>
		</div>
			<?php

			$output       = ob_get_clean();
			$result_count = count( $posts );

			if ( $submitted && '' !== $term ) {
				do_action( 'ewd_ufaq_basic_search_rendered', $identity, $term, $result_count, $args, wp_list_pluck( $posts, 'ID' ) ); }

			return apply_filters( 'ewd_ufaq_basic_search_output', $output, $identity, $args, $term, $result_count );
		}

		public static function query( $args, $term, $page ) {

			$query       = new ewdufaqQuery( $args, 'basic_search' );
			$query_args  = $query->prepare_args();

			// Optional ordering metadata must not prevent otherwise valid search
			// results from being returned. WordPress will use search relevance.
			if (
				isset( $query_args['meta_key'] )
				&& in_array(
					$query_args['meta_key'],
					array( 'ufaq_order', 'FAQ_Total_Score', 'ufaq_view_count' ),
					true
				)
			) {
				unset( $query_args['meta_key'], $query_args['orderby'] );
			}

			$raw_ceiling = isset( $args['post_count'] ) ? intval( $args['post_count'] ) : -1;
			$ceiling     = $raw_ceiling > 0 ? $raw_ceiling : 0;
			$offset      = ( max( 1, absint( $page ) ) - 1 ) * self::PAGE_SIZE;
			$remaining   = $ceiling > 0 ? max( 0, $ceiling - $offset ) : self::PAGE_SIZE + 1;
			$limit       = $ceiling > 0 ? min( self::PAGE_SIZE + 1, $remaining ) : self::PAGE_SIZE + 1;

			$query_args['post_type']        = EWD_UFAQ_FAQ_POST_TYPE;
			$query_args['post_status']      = 'publish';
			$query_args['s']                = $term;
			$query_args['posts_per_page']   = $limit;
			$query_args['offset']           = $offset;
			$query_args['suppress_filters'] = false;
			$query_args['no_found_rows']    = true;
			unset( $query_args['paged'], $query_args['faq_page'], $query_args['faqs_per_page'], $query_args['post_count'], $query_args['search_string'] );

			if ( 0 === $limit ) {
				return array(
					'posts'    => array(),
					'has_next' => false,
				); }

			$posts    = get_posts( $query_args );
			$has_next = count( $posts ) > self::PAGE_SIZE;

			if ( $has_next ) {
				array_pop( $posts ); }

			return array(
				'posts'    => $posts,
				'has_next' => $has_next,
			);
		}

		private static function render_posts( $posts, $args, $term ) {
			global $ewd_ufaq_controller;

			$faq_records = array();

			foreach ( $posts as $post ) {

				$faq = new ewdufaqFAQ();

				if ( $faq->load_post( $post ) ) {
					$faq_records[] = $faq; }
			}

			$view_args                  = array_intersect_key( $args, get_class_vars( 'ewdufaqViewFAQs' ) );
			$view_args['faq_page']      = 1;
			$view_args['faqs_per_page'] = -1;
			$view_args['post_count']    = -1;
			$view_args['search_string'] = $term;
			$view                       = new ewdufaqViewFAQs( $view_args );
			$view->is_search            = true;
			$view->set_faqs( $faq_records );

			$previous_shortcode_printing             = $ewd_ufaq_controller->shortcode_printing;
			$ewd_ufaq_controller->shortcode_printing = true;

			try {
				return $view->render();
			} finally {
				$ewd_ufaq_controller->shortcode_printing = $previous_shortcode_printing;
			}
		}

		private static function get_identity( $args ) {

			$provided = isset( $args['search_id'] ) ? sanitize_key( $args['search_id'] ) : '';

			if ( $provided && preg_match( '/^[a-z0-9_-]{1,64}$/', $provided ) ) {
				return $provided; }

			$trusted = $args;
			unset( $trusted['search_id'], $trusted['faq_page'] );
			ksort( $trusted );
			$base                       = 'ufaq_' . substr( hash( 'sha256', get_queried_object_id() . '|' . wp_json_encode( $trusted ) ), 0, 20 );
			self::$occurrences[ $base ] = isset( self::$occurrences[ $base ] ) ? self::$occurrences[ $base ] + 1 : 1;

			return $base . '_' . self::$occurrences[ $base ];
		}

		private static function get_submitted_term( $identity ) {

			$request_id = isset( $_GET['ufaq_search_id'] ) ? sanitize_key( wp_unslash( $_GET['ufaq_search_id'] ) ) : '';

			if ( ! hash_equals( $identity, $request_id ) || ! isset( $_GET['ufaq_search_term'] ) ) {
				return null; }

			$term = trim( sanitize_text_field( wp_unslash( $_GET['ufaq_search_term'] ) ) );

			return function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 200 ) : substr( $term, 0, 200 );
		}

		private static function get_form_action() {

			$url = get_permalink( get_queried_object_id() );

			return $url ? $url : home_url( '/' );
		}

		private static function print_preserved_query_fields() {

			foreach ( self::get_preserved_query_args() as $key => $value ) {

				printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $key ), esc_attr( $value ) );
			}
		}

		private static function get_preserved_query_args() {

			$allowed   = apply_filters( 'ewd_ufaq_basic_search_preserved_query_args', array( 'lang' ) );
			$preserved = array();

			foreach ( array_unique( array_map( 'sanitize_key', (array) $allowed ) ) as $key ) {

				if ( in_array( $key, array( 'ufaq_search_term', 'ufaq_search_id' ), true ) || 0 === strpos( $key, 'ufaq_search_page_' ) || ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
					continue; }

				$preserved[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}

			return $preserved;
		}

		private static function page_url( $action, $identity, $term, $page_key, $page ) {

			return add_query_arg(
				array_merge(
					self::get_preserved_query_args(),
					array(
						'ufaq_search_term' => $term,
						'ufaq_search_id'   => $identity,
						$page_key          => max( 1, absint( $page ) ),
					)
				),
				$action
			);
		}
	}
}
