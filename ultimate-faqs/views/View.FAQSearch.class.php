<?php

/**
 * Class to display an FAQ search form, and possible FAQs on load, on the front end.
 *
 * @since 2.0.0
 */
class ewdufaqViewFAQSearch extends ewdufaqViewFAQs {

	// shortcode attributes
	public $include_category_array;
	public $include_category;
	public $exclude_category;
	public $show_on_load;
	public $no_comments;
	public $orderby;
	public $order;
	public $display_all_answers;
	public $faq_page;
	public $post_count;
	public $search_id;

	/**
	 * Render the view and enqueue required stylesheets
	 * @since 2.0.0
	 */
	public function render() {
		return class_exists( 'ewdufaqEWDPHSearch', false ) ? ewdufaqEWDPHSearch::render( $this ) : ewdufaqBasicSearch::render( get_object_vars( $this ) );
	}

	/**
	 * Print the shortcode args if they will not otherwise be printed
	 * @since 2.0.0
	 */
	public function maybe_print_shortcode_args() {

		if ( $this->show_on_load ) { return; }

		$template = $this->find_template( 'faqs-shortcode-args' );
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the shortcode args if they will not otherwise be printed
	 * @since 2.0.0
	 */
	public function maybe_print_search_submit() {
		if ( class_exists( 'ewdufaqEWDPHSearch', false ) ) { ewdufaqEWDPHSearch::maybe_print_search_submit( $this ); }
	}

	/**
	 * Get the initial submit faq css classes
	 * @since 2.0.0
	 */
	public function get_classes( $classes = array() ) {
		global $ewd_ufaq_controller;

		$classes = array_merge(
			$classes,
			array(
				'ewd-ufaq-faq-list',
				'ewd-ufaq-page-type-distinct',
				'ewd-ufaq-category-tabs-',
				'ewd-ufaq-search'
			)
		);

		if ( $ewd_ufaq_controller->settings->get_setting( 'faq-category-accordion' ) ) {
			$classes[] = 'ewd-ufaq-faq-category-title-accordion';
		}

		return apply_filters( 'ewd_ufaq_faq_search_classes', $classes, $this );
	}
}
