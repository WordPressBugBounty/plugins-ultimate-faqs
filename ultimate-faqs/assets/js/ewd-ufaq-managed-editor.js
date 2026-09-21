( function( window, wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! window.ewd_ufaq_blocks ) { return; }

	var el = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var __ = wp.i18n.__;
	var config = window.ewd_ufaq_blocks;
	var saveLock = 'ewd-ufaq-managed-write';
	var pendingWrites = 0;

	function uniqueIds( value ) {
		var ids = Array.isArray( value ) ? value : String( value || '' ).split( ',' );
		var seen = {};

		return ids.map( function( id ) { return parseInt( id, 10 ); } ).filter( function( id ) {
			if ( ! id || seen[ id ] ) { return false; }
			seen[ id ] = true;
			return true;
		} );
	}

	function beginWrite() {
		pendingWrites++;
		wp.data.dispatch( 'core/editor' ).lockPostSaving( saveLock );
	}

	function endWrite() {
		pendingWrites = Math.max( 0, pendingWrites - 1 );
		if ( ! pendingWrites ) { wp.data.dispatch( 'core/editor' ).unlockPostSaving( saveLock ); }
	}

	function ajax( action, values ) {
		var body = new window.FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		body.append( 'page_post_id', config.pagePostId );
		Object.keys( values || {} ).forEach( function( key ) {
			var value = values[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function( item ) { body.append( key + '[]', item ); } );
			} else {
				body.append( key, value === undefined || value === null ? '' : value );
			}
		} );

		return window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function( response ) {
			return response.json().catch( function() { throw new Error( __( 'The FAQ request returned an invalid response.', 'ultimate-faqs' ) ); } );
		} ).then( function( response ) {
			if ( ! response.success ) {
				var error = new Error( response.data && response.data.message ? response.data.message : __( 'The FAQ request failed.', 'ultimate-faqs' ) );
				error.details = response.data || {};
				throw error;
			}
			return response.data;
		} );
	}

	function faqFromRest( record ) {
		return {
			id: record.id,
			question: record.title && ( record.title.raw || record.title.rendered ) || '',
			status: record.status,
			edit_url: config.adminEditUrl + record.id
		};
	}

	function getFaq( id ) {
		return wp.apiFetch( { url: config.faqRestBase.replace( /\/$/, '' ) + '/' + id + '?context=edit', cache: 'no-store' } ).then( faqFromRest );
	}

	function ManagedEditor( props ) {
		var attributes = props.attributes;
		var selectedIds = uniqueIds( attributes.post__in_string );
		var latestIds = useRef( selectedIds );
		latestIds.current = selectedIds;
		var state = useState( {} );
		var records = state[0];
		var setRecords = state[1];
		var searchState = useState( '' );
		var searchTerm = searchState[0];
		var setSearchTerm = searchState[1];
		var resultsState = useState( [] );
		var results = resultsState[0];
		var setResults = resultsState[1];
		var createQuestionState = useState( '' );
		var createQuestion = createQuestionState[0];
		var setCreateQuestion = createQuestionState[1];
		var createAnswerState = useState( '' );
		var createAnswer = createAnswerState[0];
		var setCreateAnswer = createAnswerState[1];
		var busyState = useState( false );
		var busy = busyState[0];
		var setBusy = busyState[1];
		var errorState = useState( '' );
		var error = errorState[0];
		var setError = errorState[1];
		var noticeState = useState( '' );
		var notice = noticeState[0];
		var setNotice = noticeState[1];
		var openState = useState( false );
		var isOpen = openState[0];
		var setIsOpen = openState[1];
		var searchSequence = useRef( 0 );
		// Session-only receipts are never serialized into the block or FAQ metadata.
		var createdFaqs = useRef( {} );
		var editedState = useState( {} );
		var editedFaqs = editedState[0];
		var setEditedFaqs = editedState[1];
		var savingState = useState( false );
		var isSaving = savingState[0];
		var setIsSaving = savingState[1];

		function setIds( ids ) {
			props.setAttributes( { post__in_string: uniqueIds( ids ).join( ',' ) } );
		}

		useEffect( function() {
			var active = true;
			var sequence = 0;
			function refreshRecords() {
				var requestSequence = ++sequence;
				selectedIds.forEach( function( id ) {
					getFaq( id ).then( function( record ) {
						if ( ! active || requestSequence !== sequence ) { return; }
						setRecords( function( current ) { var next = Object.assign( {}, current ); next[ id ] = record; return next; } );
					} ).catch( function( requestError ) {
						if ( ! active || requestSequence !== sequence ) { return; }
						if ( requestError.code === 'rest_post_invalid_id' || ( requestError.data && requestError.data.status === 404 ) ) {
							setRecords( function( current ) { var next = Object.assign( {}, current ); next[ id ] = { id: id, status: 'missing' }; return next; } );
						} else {
							setError( __( 'Some FAQs could not be refreshed. Please reload this page and try again.', 'ultimate-faqs' ) );
						}
					} );
				} );
			}
			function onVisibilityChange() {
				if ( ! window.document.hidden ) { refreshRecords(); }
			}
			refreshRecords();
			window.addEventListener( 'focus', refreshRecords );
			window.document.addEventListener( 'visibilitychange', onVisibilityChange );
			return function() {
				active = false;
				window.removeEventListener( 'focus', refreshRecords );
				window.document.removeEventListener( 'visibilitychange', onVisibilityChange );
			};
		}, [ attributes.post__in_string, isOpen ] );

		useEffect( function() {
			var manualSave = false;
			function observeSave() {
				var editor = wp.data.select( 'core/editor' );
				var saving = editor.isSavingPost();
				setIsSaving( saving );
				if ( saving && ! editor.isAutosavingPost() ) { manualSave = true; }
				if ( ! saving && manualSave ) {
					manualSave = false;
					// A failed save or an autosave must not turn a new FAQ into a saved one.
					if ( editor.didPostSaveRequestSucceed() ) { createdFaqs.current = {}; }
				}
			}
			observeSave();
			return wp.data.subscribe( observeSave );
		}, [] );

		useEffect( function() {
			var sequence = ++searchSequence.current;
			if ( searchTerm.trim().length < 2 ) { setResults( [] ); return undefined; }
			var timer = window.setTimeout( function() {
				ajax( 'ewd_ufaq_editor_search_faqs', { term: searchTerm.trim(), exclude_ids: selectedIds } )
					.then( function( data ) { if ( sequence === searchSequence.current ) { setResults( data.results || [] ); } } )
					.catch( function( requestError ) { if ( sequence === searchSequence.current ) { setError( requestError.message ); } } );
			}, 300 );
			return function() { window.clearTimeout( timer ); };
		}, [ searchTerm, attributes.post__in_string ] );

		function addExisting( result ) {
			if ( busy || isSaving || ! result.is_selectable ) { return; }
			setRecords( function( current ) {
				var next = Object.assign( {}, current );
				next[ result.id ] = { id: result.id, question: result.title, status: result.status, edit_url: result.edit_url };
				return next;
			} );
			setIds( selectedIds.concat( [ result.id ] ) );
			setSearchTerm( '' );
			setResults( [] );
		}

		function createFaq() {
			if ( busy || wp.data.select( 'core/editor' ).isSavingPost() || ! createQuestion.trim() ) { return; }
			setBusy( true ); setError( '' ); beginWrite();
			ajax( 'ewd_ufaq_editor_create_faq', {
				question: createQuestion,
				answer: createAnswer
			} ).then( function( record ) {
				if ( record.discard_token ) { createdFaqs.current[ record.id ] = record.discard_token; }
				setRecords( function( current ) { var next = Object.assign( {}, current ); next[ record.id ] = record; return next; } );
				setIds( latestIds.current.concat( [ record.id ] ) );
				setCreateQuestion( '' ); setCreateAnswer( '' );
			} ).catch( function( requestError ) { setError( requestError.message ); } )
				.then( function() { setBusy( false ); endWrite(); } );
		}

		function removeFaq( id ) {
			if ( busy || wp.data.select( 'core/editor' ).isSavingPost() ) { return; }
			setNotice( '' );
			var retainedNotice = __( 'The FAQ was removed from this block but kept in the FAQ library for safety. You can delete it from the FAQs screen if it is no longer needed.', 'ultimate-faqs' );
			var nextIds = selectedIds.filter( function( value ) { return value !== id; } );
			var token = createdFaqs.current[ id ];
			function usedInOtherBlock( blocks ) {
				return blocks.some( function( block ) {
					return ( block.clientId !== props.clientId && uniqueIds( block.attributes && block.attributes.post__in_string ).indexOf( id ) !== -1 )
						|| usedInOtherBlock( block.innerBlocks || [] );
				} );
			}
			if ( ! token || usedInOtherBlock( wp.data.select( 'core/block-editor' ).getBlocks() ) ) {
				if ( token ) { setNotice( retainedNotice ); }
				delete createdFaqs.current[ id ];
				setIds( nextIds );
				return;
			}
			setBusy( true ); setError( '' ); beginWrite();
			ajax( 'ewd_ufaq_editor_discard_created_faq', { faq_id: id, discard_token: token } )
				.then( function( data ) {
					delete createdFaqs.current[ id ];
					setIds( latestIds.current.filter( function( value ) { return value !== id; } ) );
					if ( data.discarded === false ) { setNotice( retainedNotice ); }
				} )
				.catch( function( requestError ) { setError( requestError.message ); } )
				.then( function() { setBusy( false ); endWrite(); } );
		}

		function moveFaq( id, offset ) {
			if ( busy || isSaving ) { return; }
			var from = selectedIds.indexOf( id );
			var to = from + offset;
			if ( from < 0 || to < 0 || to >= selectedIds.length ) { return; }
			var next = selectedIds.slice();
			next.splice( from, 1 ); next.splice( to, 0, id ); setIds( next );
		}

		var selected = selectedIds.map( function( id, index ) {
			var record = records[ id ];
			if ( record && ( record.status === 'trash' || record.status === 'missing' ) ) { return null; }
			function showEditReminder() {
				setEditedFaqs( function( current ) { var next = Object.assign( {}, current ); next[ id ] = true; return next; } );
			}
			return el( 'div', { className: 'ewd-ufaq-managed-item', key: id },
				record ? el( 'div', { className: 'ewd-ufaq-managed-item-summary' },
					el( 'strong', {}, record.question || __( '(Untitled FAQ)', 'ultimate-faqs' ) ),
					record.edit_url ? el( 'a', { className: 'ewd-ufaq-managed-edit-link', href: record.edit_url, target: '_blank', rel: 'noopener noreferrer', onClick: showEditReminder, onAuxClick: showEditReminder }, __( 'Edit FAQ', 'ultimate-faqs' ) ) : null,
					editedFaqs[ id ] ? el( 'span', { className: 'ewd-ufaq-managed-edit-reminder', role: 'status' }, ' (' + __( 'Save and reload this page after editing the FAQ to see your changes.', 'ultimate-faqs' ) + ')' ) : null
				) : el( Spinner ),
				el( 'div', { className: 'ewd-ufaq-managed-actions' },
					config.canOrderFaqs ? el( Button, { isSmall: true, disabled: busy || isSaving || index === 0, onClick: function() { moveFaq( id, -1 ); } }, __( 'Up', 'ultimate-faqs' ) ) : null,
					config.canOrderFaqs ? el( Button, { isSmall: true, disabled: busy || isSaving || index === selectedIds.length - 1, onClick: function() { moveFaq( id, 1 ); } }, __( 'Down', 'ultimate-faqs' ) ) : null,
					el( Button, { isSmall: true, disabled: busy || isSaving, onClick: function() { removeFaq( id ); } }, __( 'Remove', 'ultimate-faqs' ) )
				)
			);
		} );

		var controlsId = 'ewd-ufaq-managed-editor-' + props.clientId;

		return el( 'div', { className: 'ewd-ufaq-managed-editor-controls' },
			el( Button, {
				className: 'ewd-ufaq-managed-editor-toggle',
				isSecondary: true,
				'aria-controls': controlsId,
				'aria-expanded': isOpen,
				onClick: function() { setIsOpen( ! isOpen ); }
			}, isOpen ? __( 'Hide FAQ Management', 'ultimate-faqs' ) : __( 'Create a new FAQ', 'ultimate-faqs' ) ),
			isOpen ? el( 'section', { className: 'ewd-ufaq-managed-editor', id: controlsId },
				el( 'h3', {}, __( 'FAQs managed by this block', 'ultimate-faqs' ) ),
				error ? el( Notice, { status: 'error', isDismissible: true, onRemove: function() { setError( '' ); } }, error ) : null,
				notice ? el( Notice, { status: 'info', isDismissible: true, onRemove: function() { setNotice( '' ); } }, notice ) : null,
				! config.pagePostId ? el( Notice, { status: 'warning', isDismissible: false }, __( 'Save this page once before creating FAQs in the block.', 'ultimate-faqs' ) ) : null,
				selected,
				( config.hasExistingFaqs || selectedIds.length ) ? el( 'div', { className: 'ewd-ufaq-managed-search' },
					el( TextControl, { label: __( 'Find an existing FAQ by title', 'ultimate-faqs' ), value: searchTerm, onChange: setSearchTerm } ),
					results.map( function( result ) {
						return el( 'div', { className: 'ewd-ufaq-managed-result', key: result.id },
							el( 'span', {}, result.title + ' — ' + result.status_label ),
							result.is_selectable ? el( Button, { isSecondary: true, disabled: busy || isSaving, onClick: function() { addExisting( result ); } }, __( 'Use existing FAQ', 'ultimate-faqs' ) ) : null,
							! result.is_selectable && result.edit_url ? el( 'a', { href: result.edit_url, target: '_blank', rel: 'noopener noreferrer' }, __( 'Open centrally', 'ultimate-faqs' ) ) : null
						);
					} )
				) : null,
				el( 'div', { className: 'ewd-ufaq-managed-create' },
					el( 'h4', {}, __( 'Create a new FAQ', 'ultimate-faqs' ) ),
					el( TextControl, { label: __( 'Question', 'ultimate-faqs' ), value: createQuestion, onChange: setCreateQuestion } ),
					el( TextareaControl, { label: __( 'Answer', 'ultimate-faqs' ), value: createAnswer, onChange: setCreateAnswer } ),
					el( Button, { isPrimary: true, disabled: busy || isSaving || ! config.pagePostId || ! createQuestion.trim(), onClick: createFaq }, busy ? __( 'Working…', 'ultimate-faqs' ) : __( 'Create FAQ', 'ultimate-faqs' ) )
				)
			) : null
		);
	}

	window.ewdUfaqManagedEditor = ManagedEditor;
} )( window, window.wp );
