jQuery( document ).ready( function( $ ) {

	jQuery( '#ewd-ufaq-ai-faqs-open' ).removeClass( 'ewd-ufaq-hidden' ).insertAfter( jQuery( '#wpbody-content .wrap a:first' ) );

	jQuery( '#ewd-ufaq-ai-faqs-open' ).on( 'click', function() {

		jQuery( '#ewd-ufaq-ai-faqs-modal' ).removeClass( 'ewd-ufaq-hidden' );
	} );

	jQuery( '#ewd-ufaq-ai-faqs-modal, .ewd-ufaq-ai-faqs-modal-close' ).on( 'click', function() {
		
		jQuery( '#ewd-ufaq-ai-faqs-modal' ).addClass( 'ewd-ufaq-hidden' );
	});

	jQuery( '.ewd-ufaq-ai-faqs-modal-inside' ).on( 'click', function( event ) {

		event.stopPropagation();
	} );

	jQuery( '#ewd-ufaq-ai-faqs-back-to-params' ).on( 'click', function() {

		jQuery( '#ewd-ufaq-ai-faqs-params' ).removeClass( 'ewd-ufaq-hidden' );
		jQuery( '#ewd-ufaq-ai-faqs-results' ).addClass( 'ewd-ufaq-hidden' );

		jQuery( '#ewd-ufaq-ai-faqs-created-faqs' ).html( 'FAQs are being generated. This may take up to a few minutes.' );
	} );

	jQuery( '#ewd-ufaq-ai-faqs-create-button' ).on( 'click', function() {

		var selected_posts = jQuery( '#ewd-ufaq-ai-faqs-content' ).val();
		var faq_count = jQuery( '#ewd-ufaq-ai-faqs-count' ).val();
		var set_categories = jQuery( '#ewd-ufaq-ai-faqs-categories' ).val();
		var consent = jQuery( '#ewd-ufaq-ai-faqs-consent' ).is( ':checked' );

		if ( ! selected_posts || ! selected_posts.length || ! faq_count || ! consent ) {

			var error_message = consent ? 'Please complete all fields before submitting.' : 'Confirm the external content transfer before continuing.';
			jQuery( '<span>', { 'class': 'ewd-ufaq-ai-faqs-error', text: error_message } ).appendTo( '.ewd-ufaq-ai-faqs-submit' );

			jQuery( '.ewd-ufaq-ai-faqs-error' ).delay( 5000 ).fadeOut();

			return;
		}

		selected_posts = jQuery.isArray( selected_posts ) ? selected_posts : selected_posts.split( ',' );

		jQuery( '#ewd-ufaq-ai-faqs-params' ).addClass( 'ewd-ufaq-hidden' );
		jQuery( '#ewd-ufaq-ai-faqs-results' ).removeClass( 'ewd-ufaq-hidden' );

		var params = {};

    	params.nonce  				= ewd_ufaq_php_data.nonce;
    	params.action 				= 'ewd_ufaq_ai_retrieve_faqs';
    	params.selected_posts  	 	= JSON.stringify( selected_posts );
    	params.faq_count 			= faq_count;
    	params.set_categories		= set_categories;
		params.consent				= 'yes';

    	var data = jQuery.param( params );
    	jQuery.post( ajaxurl, data, function( response ) {

    		if ( !response.success ) {

    			jQuery( '#ewd-ufaq-ai-faqs-created-faqs' ).html( 'FAQs failed to create' );
    		}
    		else {

				var $results = jQuery( '#ewd-ufaq-ai-faqs-created-faqs' ).empty();

				jQuery( response.data.faqs ).each( function( index, faq ) {
					var $faq = jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq' } ).appendTo( $results );
					var $selector = jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq-checkbox' } ).appendTo( $faq );
					jQuery( '<input>', { type: 'checkbox', value: 'true', checked: true } ).appendTo( $selector );
					var $fields = jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq-title-and-content' } ).appendTo( $faq );
					jQuery( '<input>', { type: 'text' } ).val( faq.title || '' ).appendTo( jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq-title' } ).appendTo( $fields ) );
					jQuery( '<textarea>' ).val( faq.content || '' ).appendTo( jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq-content' } ).appendTo( $fields ) );

					if ( set_categories === 'yes' && Array.isArray( faq.categories ) ) {
						var $categories = jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq-categories' } ).appendTo( $fields );
						jQuery( faq.categories ).each( function( category_index, category ) {
							var category_id = Object.keys( category )[0];
							var category_name = category[ category_id ];
							var $category = jQuery( '<div>', { 'class': 'ewd-ufaq-ai-faq-category' } ).appendTo( $categories );
							jQuery( '<input>', { type: 'checkbox', value: category_id, checked: true } ).appendTo( $category );
							jQuery( '<span>' ).text( category_name ).appendTo( $category );
						} );
					}
				} );
    		}

    	} );
	} );

	jQuery( '#ewd-ufaq-ai-faqs-publish-button' ).on( 'click', function() {

		var faqs = [];

		jQuery( '.ewd-ufaq-ai-faq' ).each( function() {

			if ( ! jQuery( this ).find( '.ewd-ufaq-ai-faq-checkbox input' ).first().is( ':checked' ) ) { return; }

			var faq = {};

			faq.title = jQuery( this ).find( '.ewd-ufaq-ai-faq-title input' ).first().val();
			faq.content = jQuery( this ).find( '.ewd-ufaq-ai-faq-content textarea' ).first().val();
			faq.categories = jQuery( this ).find( '.ewd-ufaq-ai-faq-category input:checkbox:checked' ).map( function() { return jQuery( this ).val(); } ).get();

			faqs.push( faq );
		} );

		var params = {};

    	params.nonce  				= ewd_ufaq_php_data.nonce;
    	params.action 				= 'ewd_ufaq_ai_publish_faqs';
    	params.faqs  	 			= JSON.stringify( faqs );

    	var data = jQuery.param( params );
    	jQuery.post( ajaxurl, data, function( response ) {

    		if ( ! response.success ) {

    			console.log( response );
    		}
    		else {
    			location.reload();
    		}
    	} );

    	jQuery ( '#ewd-ufaq-ai-faqs-created-faqs' ).html( 'Creating FAQs...' );
	} );

} );
