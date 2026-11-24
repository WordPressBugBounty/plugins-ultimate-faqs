jQuery(function() { ufaqSetClickHandlers(); UFAQSetExpandCollapseHandlers(); });



// callback function to bring a hidden box back


function ufaqSetClickHandlers( context ) {

	var interaction_scope = context ? jQuery( context ) : jQuery( document );
	UFAQSetExpandCollapseHandlers( context );

	interaction_scope.find( '.ewd-ufaq-faq-toggle' ).off( 'click' ).on( 'click', function( event ) {
		
		jQuery( this ).attr( 'aria-expanded', function ( i, attr ) {
			return attr == 'true' ? 'false' : 'true'
		});

		event.preventDefault();
		
		var faq = jQuery( this ).parent();
		
		if ( faq.find( '.ewd-ufaq-faq-body' ).hasClass( 'ewd-ufaq-hidden' ) ) {

			EWD_UFAQ_Reveal_FAQ( faq );

			if ( typeof ewd_ufaq_php_data != 'undefined' && ewd_ufaq_php_data.faq_scroll ) { jQuery( this.ownerDocument ).find('html, body').animate({scrollTop: jQuery(this).offset().top -80}, 100); }
		}
		else {

			EWD_UFAQ_Hide_FAQ( faq );
		}
	});

	interaction_scope.find( '.ewd-ufaq-faq-category-title-toggle' ).off( 'click' ).on( 'click', function() {
		
		var category_title = jQuery( this );
		var context_document = jQuery( this.ownerDocument );
		var category = category_title.parent();
		var category_inner = category.find( '.ewd-ufaq-faq-category-inner' ).first();

		jQuery( this ).toggleClass( 'ewd-ufaq-category-tab-selected' );
		
		category_inner.toggleClass( 'ewd-ufaq-faq-category-body-hidden' );

		if ( typeof ewd_ufaq_php_data != 'undefined' && ewd_ufaq_php_data.category_accordion ) { 

			context_document.find( '.ewd-ufaq-faq-category-inner' ).each( function( index, object ) {
			
				if ( object != category_inner.get( 0 ) ) { jQuery( this ).addClass( 'ewd-ufaq-faq-category-body-hidden' ); }
			});

			context_document.find( '.ewd-ufaq-faq-category-title-toggle' ).each (function() {

				if ( this !== category_title[0] ) { jQuery( this ).removeClass( 'ewd-ufaq-category-tab-selected' ); }
			});
		}

		context_document.find( '.ewd-ufaq-faq-category-title-toggle' ).each( function() {
			var span = jQuery( this ).find( '.ewd-ufaq-category-post-margin-symbol span' );

			if ( jQuery( this).hasClass( 'ewd-ufaq-category-tab-selected' ) )  {
				span.text( span.text().toUpperCase() );
			}
			else {
				span.text( span.text().toLowerCase() );
			}
		} );
		if ( typeof window.ewdUfaqCategoryLayout === 'function' ) { window.ewdUfaqCategoryLayout( category_title ); }
	});

	interaction_scope.find( '.ewd-ufaq-back-to-top-link' ).off( 'click' ).on( 'click', function( event ) {
		event.preventDefault();

		var context_document = jQuery( this.ownerDocument );
		var list = jQuery( this ).closest( '#ewd-ufaq-faq-list' );
		if ( list.length ) { context_document.find( 'html, body' ).animate( { scrollTop: list.offset().top -80 }, 100 ); }
	});

	interaction_scope.find( '.ewd-ufaq-faq-header-link' ).off( 'click' ).on( 'click', function( event ) {
		event.preventDefault();

		var faq_id = jQuery( this ).data( 'postid' );
		var context_document = jQuery( this.ownerDocument );
		var faq = context_document.find( '#ewd-ufaq-post-' + faq_id ).first();
		if ( ! faq.length ) { return; }

		if ( faq.find( '.ewd-ufaq-faq-body' ).hasClass( 'ewd-ufaq-hidden' ) ) {
			
			EWD_UFAQ_Reveal_FAQ( faq );
		}

		context_document.find( 'html, body' ).animate( { scrollTop: faq.offset().top - 20 }, 100 );
	});
}



function EWD_UFAQ_Reveal_FAQ( faq_element ) {
	if ( ! faq_element || ! faq_element.length ) { return; }

	var post_id = faq_element.data( 'post_id' );

	var params = {};

	params.nonce   = typeof ewd_ufaq_php_data != 'undefined' ? ewd_ufaq_php_data.nonce : '';
	params.post_id = post_id;
	params.action  = 'ewd_ufaq_record_view';

	var data = jQuery.param( params );
	jQuery.post(ajaxurl, data, function(response) {});

	faq_element.find( '.ewd-ufaq-post-margin-symbol' ).each( function() { jQuery( this ).text( jQuery( this ).text().toUpperCase() ); } );

	faq_element.find( '.ewd-ufaq-faq-excerpt' ).addClass( 'ewd-ufaq-hidden' );
	
	if ( typeof ewd_ufaq_run_effect === 'function' && typeof ewd_ufaq_php_data != 'undefined' && ewd_ufaq_php_data.reveal_effect && ewd_ufaq_php_data.reveal_effect != 'none' ) { ewd_ufaq_run_effect( 'show', faq_element ); }
	else { faq_element.find( '.ewd-ufaq-faq-body' ).removeClass( 'ewd-ufaq-hidden' ); }
			
	if ( typeof ewd_ufaq_php_data != 'undefined' && ewd_ufaq_php_data.faq_accordion ) { 

		jQuery( faq_element[0].ownerDocument ).find( '.ewd-ufaq-faq-div' ).each( function() {

			if ( jQuery( this ).prop( 'id' ) != faq_element.prop( 'id' ) ) {

				EWD_UFAQ_Hide_FAQ( jQuery(this) );
			} 
			else {

				jQuery( this ).addClass( 'ewd-ufaq-post-active' );
			}
		});
	}
	else {

		faq_element.addClass( 'ewd-ufaq-post-active' );
	}
}

function EWD_UFAQ_Hide_FAQ( faq_element ) {
	if ( ! faq_element || ! faq_element.length ) { return; }

	var post_id = faq_element.data( 'post_id' );

	faq_element.find( '.ewd-ufaq-faq-excerpt' ).removeClass( 'ewd-ufaq-hidden' );

	if ( typeof ewd_ufaq_run_effect === 'function' && typeof ewd_ufaq_php_data != 'undefined' && ewd_ufaq_php_data.reveal_effect && ewd_ufaq_php_data.reveal_effect != 'none' && ! faq_element.find( '.ewd-ufaq-faq-body' ).hasClass( 'ewd-ufaq-hidden' ) ) { ewd_ufaq_run_effect( 'hide', faq_element ); }
	else { faq_element.find( '.ewd-ufaq-faq-body' ).addClass( 'ewd-ufaq-hidden' ); }

	faq_element.removeClass( 'ewd-ufaq-post-active' );
	faq_element.find( '.ewd-ufaq-post-margin-symbol' ).each( function() { jQuery( this ).text( jQuery( this ).text().toLowerCase() ); } );
}

jQuery(document).ready(function() {
	if ( typeof ewd_ufaq_php_data != 'undefined' && ewd_ufaq_php_data.display_faq > 0 ) {
		
		var faq = jQuery( '.ewd-ufaq-faq-div[data-post_id="' + ewd_ufaq_php_data.display_faq + '"]' );

		faq.parent().removeClass( 'ewd-ufaq-hidden' );

		EWD_UFAQ_Reveal_FAQ( faq );

		jQuery('html, body').animate({scrollTop: faq.offset().top -80}, 100);
	}
});





function UFAQSetExpandCollapseHandlers( context ) {
	var interaction_scope = context ? jQuery( context ) : jQuery( document );

	interaction_scope.find('.ewd-ufaq-expand-all').off('click').on('click', function() {
		var context_document = jQuery( this.ownerDocument );
		
		var accordion_setting = typeof ewd_ufaq_php_data != 'undefined' ? ewd_ufaq_php_data.faq_accordion : false;
		if ( typeof ewd_ufaq_php_data != 'undefined' ) { ewd_ufaq_php_data.faq_accordion = false; } // turn FAQ accordion off while expanding all

		context_document.find( '.ewd-ufaq-faq-toggle' ).each( function() {

			var faq = jQuery(this).parent();

			jQuery(this).attr('aria-expanded', 'true');

			EWD_UFAQ_Reveal_FAQ( faq );
		});

		if ( typeof ewd_ufaq_php_data != 'undefined' ) { ewd_ufaq_php_data.faq_accordion = accordion_setting; } //reset FAQ accordion setting 

		context_document.find('.ewd-ufaq-faq-category-inner').removeClass('ewd-ufaq-faq-category-body-hidden');
		context_document.find('.ewd-ufaq-collapse-all').removeClass('ewd-ufaq-hidden');
		context_document.find('.ewd-ufaq-expand-all').addClass('ewd-ufaq-hidden');

		context_document.find( '.ewd-ufaq-faq-category-title-toggle' ).addClass( 'ewd-ufaq-category-tab-selected' );
		context_document.find( '.ewd-ufaq-category-post-margin-symbol span' ).each( function() {
			jQuery( this ).text( jQuery( this ).text().toUpperCase() );
		});
	});

	interaction_scope.find('.ewd-ufaq-collapse-all').off('click').on('click', function() {
		var context_document = jQuery( this.ownerDocument );

		context_document.find('.ewd-ufaq-faq-toggle').each(function() {

			var faq = jQuery(this).parent();

			jQuery(this).attr('aria-expanded', 'false');

			EWD_UFAQ_Hide_FAQ( faq );
		});

		context_document.find( '.ewd-ufaq-faq-category-inner' ).addClass( 'ewd-ufaq-faq-category-body-hidden' );
		context_document.find( '.ewd-ufaq-faq-category-title-toggle' ).removeClass( 'ewd-ufaq-category-tab-selected' );

		context_document.find( '.ewd-ufaq-category-post-margin-symbol span' ).each( function() {
			jQuery( this ).text( jQuery( this ).text().toLowerCase() );
		});

		context_document.find('.ewd-ufaq-expand-all').removeClass('ewd-ufaq-hidden');
		context_document.find('.ewd-ufaq-collapse-all').addClass('ewd-ufaq-hidden');
	});
}







/*jQuery(document).ready(function() {
jQuery('a[href*=#]:not([href=#])').click(function() {
	var post_id = jQuery(this).attr("data-postid"); 
	var selectedIDString = 'ewd-ufaq-body-'+post_id;
	
	if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
	var target = jQuery(this.hash);
	target = target.length ? target : jQuery('[name=' + this.hash.slice(1) +']');
	if (target.length) {

	jQuery('html,body').on("scroll mousedown wheel DOMMouseScroll mousewheel keyup touchmove", function(){
	jQuery('html,body').stop();
	});
		
		if (jQuery('#'+selectedIDString).hasClass("ewd-ufaq-hidden")) {
			EWD_UFAQ_Reveal_FAQ(post_id, selectedIDString);
		}

		jQuery('html,body').animate({
		scrollTop: target.offset().top
		}, 1000);
		//return false;
	}
	}
});
});*/

