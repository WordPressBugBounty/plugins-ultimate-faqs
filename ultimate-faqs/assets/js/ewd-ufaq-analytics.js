( function( window, document ) {
	'use strict';
	var config = window.ewdUfaqAnalytics;
	if ( ! config || ! config.endpoint || ! Array.isArray( config.contexts ) ) { return; }

	function storageHas( key, persistent ) {
		try {
			var storage = persistent ? window.localStorage : window.sessionStorage;
			var recorded = parseInt( storage.getItem( key ), 10 );
			if ( recorded && ( ! persistent || Date.now() - recorded < 30 * 24 * 60 * 60 * 1000 ) ) { return true; }
			storage.setItem( key, String( Date.now() ) );
			return false;
		} catch ( error ) { return false; }
	}

	function emit( context ) {
		var key = 'ewdUfaqAnalytics:' + context.storage_key;
		var persistent = context.event === 'helpful_yes' || context.event === 'helpful_no';
		if ( storageHas( key, persistent ) ) { return; }
		var body = JSON.stringify( { event: context.event, token: context.token } );
		if ( window.navigator.sendBeacon ) {
			window.navigator.sendBeacon( config.endpoint, new window.Blob( [ body ], { type: 'application/json' } ) );
		} else {
			window.fetch( config.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true } ).catch( function() {} );
		}
	}

	function findContext( event, faqId ) {
		return config.contexts.find( function( context ) { return context.event === event && ( ! faqId || parseInt( context.faq_id, 10 ) === faqId ); } );
	}

	config.contexts.filter( function( context ) { return context.event === 'search'; } ).forEach( emit );

	document.addEventListener( 'click', function( click ) {
		var rating = click.target.closest( '.ewd-ufaq-rating-button' );
		var faq = click.target.closest( '.ewd-ufaq-faq-div' );
		if ( rating ) {
			var ratingId = parseInt( rating.getAttribute( 'data-faq_id' ), 10 );
			var ratingEvent = rating.classList.contains( 'ewd-ufaq-up-vote' ) ? 'helpful_yes' : 'helpful_no';
			var ratingContext = findContext( ratingEvent, ratingId );
			if ( ratingContext ) { emit( ratingContext ); }
		} else if ( faq && click.target.closest( '.ewd-ufaq-faq-title' ) ) {
			var faqId = parseInt( faq.getAttribute( 'data-post_id' ), 10 );
			var openContext = findContext( 'open', faqId );
			if ( openContext ) { emit( openContext ); }
		}
	} );
} )( window, document );
