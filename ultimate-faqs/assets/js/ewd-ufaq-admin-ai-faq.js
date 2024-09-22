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

		selected_posts = jQuery( '#ewd-ufaq-ai-faqs-content' ).val();
		faq_count = jQuery( '#ewd-ufaq-ai-faqs-count' ).val();
		set_categories = jQuery( '#ewd-ufaq-ai-faqs-categories' ).val();

		if ( ! selected_posts.length || ! faq_count ) {

			var error_message = '<span class=\'ewd-ufaq-ai-faqs-error\'>Please complete all fields before submitting.</span>';

			jQuery( '.ewd-ufaq-ai-faqs-submit' ).append( error_message );

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

    	var data = jQuery.param( params );
    	jQuery.post( ajaxurl, data, function( response ) {

    		if ( !response.success ) {

    			jQuery( '#ewd-ufaq-ai-faqs-created-faqs' ).html( 'FAQs failed to create' );
    		}
    		else {

    			faq_content = '';

    			jQuery( response.data.faqs ).each( function( index, faq ) {

    				faq_content += '<div class=\'ewd-ufaq-ai-faq\'>';

    				faq_content += '<div class=\'ewd-ufaq-ai-faq-checkbox\'>';
    				faq_content += '<input type=\'checkbox\' value=\'true\' checked=\'checked\' />';
    				faq_content += '</div>';

    				faq_content += '<div class=\'ewd-ufaq-ai-faq-title-and-content\'>';

					faq_content += '<div class=\'ewd-ufaq-ai-faq-title\'>';
    				faq_content += '<input type=\'text\' value=\'' + faq.title + '\' />';
    				faq_content += '</div>';

    				faq_content += '<div class=\'ewd-ufaq-ai-faq-content\'>';
    				faq_content += '<textarea>' + faq.content + '</textarea>';
    				faq_content += '</div>';

    				if ( set_categories == 'yes' ) {

    					faq_content += '<div class=\'ewd-ufaq-ai-faq-categories\'>';
    					
    					jQuery( faq.categories ).each( function( index, category ) {

    						faq_content += '<div class=\'ewd-ufaq-ai-faq-category\'>';
    						faq_content += '<input type=\'checkbox\' value=\'' + Object.keys(category)[0] + '\' checked=\'checked\' />';
    						faq_content += '<span>' + Object.values(category)[0] + '</span>';
    						faq_content += '</div>';
    					} );

    					faq_content += '</div>';
    				}

					faq_content += '</div>';

    				faq_content += '</div>';
    			} );

    			jQuery( '#ewd-ufaq-ai-faqs-created-faqs' ).html( faq_content );
    		}

    	} );
	} );

	jQuery( '#ewd-ufaq-ai-faqs-publish-button' ).on( 'click', function() {

		faqs = [];

		jQuery( '.ewd-ufaq-ai-faq' ).each( function() {

			if ( ! jQuery( this ).find( '.ewd-ufaq-ai-faq-checkbox input' ).first().is( ':checked' ) ) { return; }

			faq = {};

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