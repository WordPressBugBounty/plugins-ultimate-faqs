( function() {
	'use strict';
	if ( ! window.location.search ) { return; }
	var search = document.querySelector( '.ewd-ufaq-basic-search[data-ewd-ufaq-search-submitted="true"]' );
	if ( ! search ) { return; }
	var results = search.querySelector( '.ewd-ufaq-basic-search-results' );
	if ( results ) { results.focus( { preventScroll: true } ); }
} )();
