<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'ewdufaqBlocks' ) ) {
/**
 * Class to handle plugin Gutenberg blocks
 *
 * @since 2.0.0
 */
class ewdufaqBlocks {

	public function __construct() {

		add_action( 'init', array( $this, 'add_faq_blocks' ) );
			add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_content_assets' ) );
		
		add_filter( 'block_categories_all', array( $this, 'add_block_category' ) );
	}

	/**
	 * Add the Gutenberg block to the list of available blocks
	 * @since 2.0.0
	 */
	public function add_faq_blocks() {

		if ( ! function_exists( 'render_block_core_block' ) ) { return; }

		$this->enqueue_assets();   

		$args = array(
			'attributes' => array(
				'post_count' => array(
					'type' => 'string',
					'default' => -1
				),
				'group_by_category' => array(
					'type' => 'string',
				),
				'faq_accordion' => array(
					'type' => 'string',
				),
				'category_accordion' => array(
					'type' => 'string',
				),
				'include_category_array' => array(
					'type' => 'object',
				),
				'include_category' => array(
					'type' => 'string',
				),
				'exclude_category' => array(
					'type' => 'string',
				),
				'post__in_string' => array(
					'type' => 'string',
				),
			),
			'render_callback' 	=> 'ewd_ufaq_faqs_shortcode',
		);

		register_block_type( 'ultimate-faqs/ewd-ufaq-display-faq-block', $args );

		$args = array(
			'attributes' => array(
				'post_count' => array(
					'type' => 'string',
					'default' => -1
				),
			),
			'render_callback' 	=> 'ewd_ufaq_recent_faqs_shortcode',
		);

		register_block_type( 'ultimate-faqs/ewd-ufaq-recent-faqs-block', $args );

		$args = array(
			'attributes' => array(
				'post_count' => array(
					'type' => 'string',
					'default' => -1
				),
			),
			'render_callback' 	=> 'ewd_ufaq_popular_faqs_shortcode',
		);

		register_block_type( 'ultimate-faqs/ewd-ufaq-popular-faqs-block', $args );

		$args = array(
			'attributes' => array(
				'show_on_load' => array(
					'type' => 'string',
				),
				'include_category_array' => array(
					'type' => 'object',
				),
				'include_category' => array(
					'type' => 'string',
				),
				'exclude_category' => array(
					'type' => 'string',
				),
				'search_id' => array(
					'type' => 'string',
				),
			),
			'render_callback' 	=> 'ewd_ufaq_search_faqs_shortcode',
		);

		register_block_type( 'ultimate-faqs/ewd-ufaq-search-block', $args );

		$args = array(
			'render_callback' 	=> 'ewd_ufaq_submit_faq_shortcode',
		);

		register_block_type( 'ultimate-faqs/ewd-ufaq-submit-faq-block', $args );

		add_action( 'current_screen', array( $this, 'localize_data' ) );
	}

	/**
	 * Conditionally enqueue assets and perform any necessary data localization for use in block parameters
	 * @since 2.1.13
	 */
		public function enqueue_editor_content_assets() {
			global $ewd_ufaq_controller;
			if ( ! is_admin() ) {
				return;
			}
			// This hook is replayed inside Gutenberg's canvas iframe. current_screen
			// alone enqueues only in the parent window, leaving previews unstyled.
			$ewd_ufaq_controller->register_assets();
			wp_enqueue_style( 'ewd-ufaq-css' );
			wp_enqueue_style( 'ewd-ufaq-blocks-css' );
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && ( $screen->is_block_editor() || 'widgets' === $screen->id ) ) {
				wp_enqueue_script( 'ewd-ufaq-js' );
				$args = array( 'nonce' => wp_create_nonce( 'ewd-ufaq-js' ), 'faq_accordion' => $ewd_ufaq_controller->settings->get_setting( 'faq-accordion' ), 'category_accordion' => $ewd_ufaq_controller->settings->get_setting( 'faq-category-accordion' ), 'faq_scroll' => false );
				wp_add_inline_script( 'ewd-ufaq-js', 'window.ajaxurl=' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';window.ewd_ufaq_php_data=' . wp_json_encode( apply_filters( 'ewd_ufaq_js_localize_data', $args, null ) ) . ';', 'before' );
			}
		}

	public function localize_data() {

		global $post, $ewd_ufaq_controller;
		$post_id = $post instanceof WP_Post ? $post->ID : ( isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0 );
		$editor_post = $post_id ? get_post( $post_id ) : null;
		$screen = get_current_screen();

		if ( ! $screen->is_block_editor and $screen->id != 'widgets' ) { return; }

		wp_enqueue_style( 'ewd-ufaq-css' );
		wp_enqueue_style( 'ewd-ufaq-blocks-css' );
		wp_enqueue_script( 'ewd-ufaq-js' );
		wp_enqueue_script( 'ewd-ufaq-blocks-js' );

			$category_taxonomy = get_taxonomy( EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
		$existing_faq_ids = get_posts( array(
			'post_type'      => EWD_UFAQ_FAQ_POST_TYPE,
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		$can_order_faqs = is_object( $ewd_ufaq_controller )
			&& is_object( $ewd_ufaq_controller->permissions )
			&& $ewd_ufaq_controller->permissions->check_permission( 'ordering' );

		wp_add_inline_script(
			'ewd-ufaq-managed-editor-js',
			sprintf(
				'var ewd_ufaq_blocks = window.ewd_ufaq_blocks = %s;',
				wp_json_encode( array(
					'canAssignCategories' => $category_taxonomy && current_user_can( $category_taxonomy->cap->assign_terms ),
					'categoryRestPath'    => '/wp/v2/' . ( $category_taxonomy && $category_taxonomy->rest_base ? $category_taxonomy->rest_base : EWD_UFAQ_FAQ_CATEGORY_TAXONOMY ),
					'hasExistingFaqs'    => ! empty( $existing_faq_ids ),
					'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
					'nonce'              => wp_create_nonce( 'ewd-ufaq-editor' ),
					'pagePostId'         => $editor_post instanceof WP_Post ? $editor_post->ID : 0,
					'faqRestBase'        => rest_url( 'wp/v2/' . EWD_UFAQ_FAQ_POST_TYPE ),
					'adminEditUrl'       => admin_url( 'post.php?action=edit&post=' ),
					'canOrderFaqs'       => $can_order_faqs,
				) )
			),
			'before'
		);

	}
	
	/**
	 * Create a new category of blocks to hold our block
	 * @since 2.0.0
	 */
	public function add_block_category( $categories ) {
		
		$categories[] = array(
			'slug'  => 'ewd-ufaq-blocks',
			'title' => __( 'Ultimate FAQs', 'ultimate-faqs' ),
		);

		return $categories;
	}

	/**
	 * Register the necessary JS and CSS to display the block in the editor
	 * @since 2.0.0
	 */
	public function enqueue_assets() {

		wp_register_style( 'ewd-ufaq-css', EWD_UFAQ_PLUGIN_URL . '/assets/css/ewd-ufaq.css', array(), EWD_UFAQ_VERSION );
		wp_register_style( 'ewd-ufaq-blocks-css', EWD_UFAQ_PLUGIN_URL . '/assets/css/ewd-ufaq-blocks.css', array( 'wp-edit-blocks' ), EWD_UFAQ_VERSION );
		wp_register_script( 'ewd-ufaq-js', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq.js', array( 'jquery', 'jquery-ui-core' ), EWD_UFAQ_VERSION, true );
		wp_register_script( 'ewd-ufaq-managed-editor-js', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq-managed-editor.js', array( 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ), EWD_UFAQ_VERSION, true );
		wp_register_script( 'ewd-ufaq-blocks-js', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq-blocks.js', array( 'ewd-ufaq-js', 'ewd-ufaq-managed-editor-js', 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-server-side-render' ), EWD_UFAQ_VERSION, true );
	}
}

}
