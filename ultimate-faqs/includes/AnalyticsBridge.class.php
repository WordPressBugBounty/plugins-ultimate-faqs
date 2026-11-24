<?php
/** Compatibility facade. Core render/search events do not collect analytics in Free. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ewdufaqAnalyticsBridge {
	public function __construct() {}
	public function advanced_search_context( $context, $term, $count, $args ) {
		return class_exists( 'ewdufaqEWDPHAnalyticsBridge', false ) ? ewdufaqEWDPHAnalyticsBridge::instance()->advanced_search_context( $context, $term, $count, $args ) : $context;
	}
	public function register_search( $identity, $term, $count, $args, $ids = array() ) {
		if ( class_exists( 'ewdufaqEWDPHAnalyticsBridge', false ) ) { ewdufaqEWDPHAnalyticsBridge::instance()->register_search( $identity, $term, $count, $args, $ids ); }
	}
	public function register_faq( $id, $view ) {
		if ( class_exists( 'ewdufaqEWDPHAnalyticsBridge', false ) ) { ewdufaqEWDPHAnalyticsBridge::instance()->register_faq( $id, $view ); }
	}
	public function print_contexts() {
		if ( class_exists( 'ewdufaqEWDPHAnalyticsBridge', false ) ) { ewdufaqEWDPHAnalyticsBridge::instance()->print_contexts(); }
	}
}
