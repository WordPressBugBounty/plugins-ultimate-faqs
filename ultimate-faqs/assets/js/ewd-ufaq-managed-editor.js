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
	var ComboboxControl = wp.components.ComboboxControl;
	var Modal = wp.components.Modal;
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

	function ajax( action, values, signal ) {
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
			body: body,
			signal: signal
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
			can_delete: Boolean( record.ewd_ufaq_can_delete ),
			edit_url: config.adminEditUrl + record.id
		};
	}

	function getFaq( id ) {
		var url = new window.URL( config.faqRestBase, window.location.href );
		if ( url.searchParams.has( 'rest_route' ) ) {
			url.searchParams.set( 'rest_route', url.searchParams.get( 'rest_route' ).replace( /\/$/, '' ) + '/' + id );
		} else {
			url.pathname = url.pathname.replace( /\/$/, '' ) + '/' + id;
		}
		url.searchParams.set( 'context', 'edit' );
		url.searchParams.set( '_fields', 'id,title,status,ewd_ufaq_can_delete' );
		return wp.apiFetch( { url: url.toString(), cache: 'no-store' } ).then( faqFromRest );
	}

	/** One bounded, abortable page; chosen terms remain independent of search results. */
	function CategorySelector( props ) {
		var termState = useState( '' ), term = termState[0], setTerm = termState[1];
		var pageState = useState( 1 ), page = pageState[0], setPage = pageState[1];
		var dataState = useState( { results: [], has_more: false } ), data = dataState[0], setData = dataState[1];
		var loadingState = useState( false ), loading = loadingState[0], setLoading = loadingState[1];
		var errorState = useState( '' ), error = errorState[0], setError = errorState[1];
		var retryState = useState( 0 ), retry = retryState[0], setRetry = retryState[1];
		var sequence = useRef( 0 );
		useEffect( function() {
			var current = ++sequence.current;
			var controller = new window.AbortController();
			setLoading( true ); setError( '' );
			var timer = window.setTimeout( function() {
				// Display filters read public terms only; they do not assign FAQ taxonomy.
				// Core REST pagination also works in widgets and unsaved page inspectors.
				var request = props.purpose === 'display' ? wp.apiFetch( {
					path: config.categoryRestPath + '?per_page=20&page=' + page + '&hide_empty=false&orderby=name&order=asc&search=' + encodeURIComponent( term ) + '&_fields=id,name,slug,parent',
					parse: false, signal: controller.signal
				} ).then( function( response ) {
					return response.json().then( function( terms ) { return {
						results: terms.map( function( category ) { return { id: category.id, name: category.name, label: category.name, slug: category.slug }; } ),
						has_more: page < Number( response.headers.get( 'X-WP-TotalPages' ) )
					}; } );
				} ) : ajax( 'ewd_ufaq_editor_search_categories', { term: term, result_page: page }, controller.signal );
				request.then( function( result ) {
					if ( current === sequence.current ) { setData( result ); setLoading( false ); }
				} ).catch( function( requestError ) {
					if ( current === sequence.current && requestError.name !== 'AbortError' ) { setError( requestError.message ); setLoading( false ); }
				} );
			}, 300 );
			return function() { window.clearTimeout( timer ); controller.abort(); };
		}, [ term, page, retry, props.purpose ] );
		return el( 'div', { className: 'ewd-ufaq-category-selector' },
			el( ComboboxControl, {
				className: 'ewd-ufaq-category-combobox',
				label: props.label || __( 'FAQ Categories', 'ultimate-faqs' ),
				value: null,
				options: loading ? [] : ( data.results || [] ).map( function( category ) { return { value: String( category.id ), label: category.label }; } ),
				disabled: props.disabled,
				onFilterValueChange: function( value ) { setTerm( value ); setPage( 1 ); },
				onChange: function( value ) {
					var category = ( data.results || [] ).find( function( item ) { return String( item.id ) === value; } );
					if ( category ) { props.onSelect( category ); }
				}
			} ),
			el( 'div', { role: 'status', 'aria-live': 'polite' }, loading ? __( 'Loading categories…', 'ultimate-faqs' ) : __( 'Categories on this page:', 'ultimate-faqs' ) + ' ' + ( data.results || [] ).length ),
			! loading && ! error && ! ( data.results || [] ).length ? el( 'p', {}, __( 'No existing categories found.', 'ultimate-faqs' ) ) : null,
			error ? el( Notice, { status: 'error', isDismissible: false }, error,
				el( Button, { isSmall: true, onClick: function() { setRetry( retry + 1 ); } }, __( 'Retry loading categories', 'ultimate-faqs' ) ) ) : null,
			page > 1 || data.has_more ? el( 'div', { className: 'ewd-ufaq-category-pages' },
				el( Button, { isSmall: true, disabled: props.disabled || loading || page === 1, onClick: function() { setPage( page - 1 ); } }, __( 'Previous categories', 'ultimate-faqs' ) ),
				el( Button, { isSmall: true, disabled: props.disabled || loading || ! data.has_more, onClick: function() { setPage( page + 1 ); } }, __( 'Next categories', 'ultimate-faqs' ) ) ) : null
		);
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
		var categoryState = useState( [] ), categories = categoryState[0], setCategories = categoryState[1];
		var partialState = useState( {} ), partials = partialState[0], setPartials = partialState[1];
		var trashState = useState( null ), trashId = trashState[0], setTrashId = trashState[1];
		var trashingState = useState( {} ), trashing = trashingState[0], setTrashing = trashingState[1];
		var trashRequests = useRef( {} );
		var recordGenerations = useRef( {} );
		var searchPageState = useState( 1 ), searchPage = searchPageState[0], setSearchPage = searchPageState[1];
		var moreState = useState( false ), hasMore = moreState[0], setHasMore = moreState[1];
		var searchingState = useState( false ), searching = searchingState[0], setSearching = searchingState[1];

		function setIds( ids ) {
			props.setAttributes( { post__in_string: uniqueIds( ids ).join( ',' ) } );
		}

		useEffect( function() {
			var active = true;
			var sequence = 0;
			function refreshRecords() {
				var requestSequence = ++sequence;
				selectedIds.forEach( function( id ) {
					if ( trashRequests.current[ id ] ) { return; }
					var generation = recordGenerations.current[ id ] || 0;
					getFaq( id ).then( function( record ) {
						if ( ! active || requestSequence !== sequence || generation !== ( recordGenerations.current[ id ] || 0 ) ) { return; }
						setRecords( function( current ) { var next = Object.assign( {}, current ); next[ id ] = record; return next; } );
					} ).catch( function( requestError ) {
						if ( ! active || requestSequence !== sequence || generation !== ( recordGenerations.current[ id ] || 0 ) ) { return; }
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
			if ( searchTerm.trim().length < 2 ) { setResults( [] ); setHasMore( false ); setSearching( false ); return undefined; }
			var controller = new window.AbortController();
			setSearching( true ); setResults( [] );
			var timer = window.setTimeout( function() {
				ajax( 'ewd_ufaq_editor_search_faqs', { term: searchTerm.trim(), exclude_ids: selectedIds, result_page: searchPage }, controller.signal )
					.then( function( data ) { if ( sequence === searchSequence.current ) { setResults( data.results || [] ); setHasMore( Boolean( data.has_more ) ); setSearching( false ); } } )
					.catch( function( requestError ) { if ( sequence === searchSequence.current && requestError.name !== 'AbortError' ) { setError( requestError.message ); setSearching( false ); } } );
			}, 300 );
			return function() { window.clearTimeout( timer ); controller.abort(); };
		}, [ searchTerm, searchPage, attributes.post__in_string ] );

		function addExisting( result ) {
			if ( busy || isSaving || ! result.is_selectable ) { return; }
			setRecords( function( current ) {
				var next = Object.assign( {}, current );
				next[ result.id ] = { id: result.id, question: result.title, status: result.status, edit_url: result.edit_url, can_delete: result.can_delete };
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
				answer: createAnswer,
				category_ids: categories.map( function( category ) { return category.id; } )
			} ).then( function( record ) {
				if ( record.discard_token ) { createdFaqs.current[ record.id ] = record.discard_token; }
				setRecords( function( current ) { var next = Object.assign( {}, current ); next[ record.id ] = record; return next; } );
				setIds( latestIds.current.concat( [ record.id ] ) );
				if ( record.partial_failure ) {
					setPartials( function( current ) { var next = Object.assign( {}, current ); next[ record.id ] = { token: record.discard_token, ids: categories.map( function( category ) { return category.id; } ), message: record.category_error }; return next; } );
				}
				setCategories( [] );
				setCreateQuestion( '' ); setCreateAnswer( '' );
			} ).catch( function( requestError ) { setError( requestError.message ); } )
				.then( function() { setBusy( false ); endWrite(); } );
		}

		function retryCategories( id ) {
			if ( busy || isSaving || ! partials[ id ] ) { return; }
			setBusy( true ); setError( '' ); beginWrite();
			ajax( 'ewd_ufaq_editor_retry_categories', { faq_id: id, discard_token: partials[ id ].token, category_ids: partials[ id ].ids } ).then( function( data ) {
				if ( data.partial_failure ) { setError( data.category_error ); return; }
				setPartials( function( current ) { var next = Object.assign( {}, current ); delete next[ id ]; return next; } );
				// Server preview must refetch even though the ID list is unchanged.
				window.dispatchEvent( new window.Event( 'ewd-ufaq-preview-refresh' ) );
				setNotice( __( 'Categories assigned. Reload the preview if it has not refreshed.', 'ultimate-faqs' ) );
			} ).catch( function( requestError ) { setError( requestError.message ); } ).then( function() { setBusy( false ); endWrite(); } );
		}

		function trashFaq() {
			if ( busy || isSaving || ! trashId || trashRequests.current[ trashId ] ) { return; }
			var id = trashId;
			recordGenerations.current[ id ] = ( recordGenerations.current[ id ] || 0 ) + 1;
			trashRequests.current[ id ] = true;
			setTrashing( Object.assign( {}, trashRequests.current ) );
			setError( '' ); setTrashId( null );
			setNotice( __( 'Moving FAQ to Trash… You can continue editing while this completes.', 'ultimate-faqs' ) );
			// Explicit Trash retains all block attributes. Unlike creation/discard,
			// it need not lock page saving or hold the confirmation modal open.
			ajax( 'ewd_ufaq_editor_trash_faq', { faq_id: id, discard_token: createdFaqs.current[ id ] || '' } ).then( function( data ) {
				delete createdFaqs.current[ id ];
				setRecords( function( current ) { var next = Object.assign( {}, current ); next[ id ] = { id: id, status: data.status }; return next; } );
				window.dispatchEvent( new window.Event( 'ewd-ufaq-preview-refresh' ) );
				setNotice( data.status === 'missing' ? __( 'This FAQ no longer exists. Its block references have been retained, but it is hidden from the active list.', 'ultimate-faqs' ) : __( 'FAQ moved to Trash. Its references have been retained so a central restore can make it visible again.', 'ultimate-faqs' ) );
			} ).catch( function( requestError ) { setError( requestError.message ); setNotice( '' ); } ).then( function() {
				delete trashRequests.current[ id ];
				setTrashing( Object.assign( {}, trashRequests.current ) );
			} );
		}

		function removeFaq( id ) {
			if ( busy || trashRequests.current[ id ] || wp.data.select( 'core/editor' ).isSavingPost() ) { return; }
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
			if ( busy || isSaving || trashRequests.current[ id ] ) { return; }
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
			return el( 'div', { className: 'ewd-ufaq-managed-item', key: id, 'aria-busy': Boolean( trashing[ id ] ) },
				record ? el( 'div', { className: 'ewd-ufaq-managed-item-summary' },
					el( 'strong', {}, record.question || __( '(Untitled FAQ)', 'ultimate-faqs' ) ),
					record.edit_url ? el( 'a', { className: 'ewd-ufaq-managed-edit-link', href: record.edit_url, target: '_blank', rel: 'noopener noreferrer', onClick: showEditReminder, onAuxClick: showEditReminder }, __( 'Edit FAQ', 'ultimate-faqs' ) ) : null,
					editedFaqs[ id ] ? el( 'span', { className: 'ewd-ufaq-managed-edit-reminder', role: 'status' }, ' (' + __( 'Save and reload this page after editing the FAQ to see your changes.', 'ultimate-faqs' ) + ')' ) : null
				) : el( Spinner ),
				partials[ id ] ? el( Notice, { status: 'warning', isDismissible: false }, partials[ id ].message,
					el( Button, { isSecondary: true, disabled: busy || isSaving, onClick: function() { retryCategories( id ); } }, __( 'Retry category assignment', 'ultimate-faqs' ) ) ) : null,
				el( 'div', { className: 'ewd-ufaq-managed-actions' },
					config.canOrderFaqs ? el( Button, { isSmall: true, disabled: busy || isSaving || trashing[ id ] || index === 0, onClick: function() { moveFaq( id, -1 ); } }, __( 'Up', 'ultimate-faqs' ) ) : null,
					config.canOrderFaqs ? el( Button, { isSmall: true, disabled: busy || isSaving || trashing[ id ] || index === selectedIds.length - 1, onClick: function() { moveFaq( id, 1 ); } }, __( 'Down', 'ultimate-faqs' ) ) : null,
					el( Button, { isSmall: true, disabled: busy || isSaving || trashing[ id ], onClick: function() { removeFaq( id ); } }, __( 'Remove', 'ultimate-faqs' ) ),
					record && record.can_delete ? el( Button, { isSmall: true, isDestructive: true, disabled: busy || isSaving || trashing[ id ], onClick: function() { setTrashId( id ); } }, trashing[ id ] ? __( 'Moving FAQ to Trash…', 'ultimate-faqs' ) : __( 'Move FAQ to Trash', 'ultimate-faqs' ) ) : null
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
					el( TextControl, { label: __( 'Find an existing FAQ by title', 'ultimate-faqs' ), value: searchTerm, onChange: function( value ) { setSearchTerm( value ); setSearchPage( 1 ); } } ),
					el( 'div', { role: 'status', 'aria-live': 'polite' }, searching ? __( 'Searching FAQs…', 'ultimate-faqs' ) : __( 'FAQs on this page:', 'ultimate-faqs' ) + ' ' + results.length ),
					results.map( function( result ) {
						return el( 'div', { className: 'ewd-ufaq-managed-result', key: result.id },
							el( 'span', {}, result.title + ' — ' + result.status_label ),
							result.is_selectable ? el( Button, { isSecondary: true, disabled: busy || isSaving, onClick: function() { addExisting( result ); } }, __( 'Use existing FAQ', 'ultimate-faqs' ) ) : null,
							! result.is_selectable && result.edit_url ? el( 'a', { href: result.edit_url, target: '_blank', rel: 'noopener noreferrer' }, __( 'Open centrally', 'ultimate-faqs' ) ) : null
						);
					} ),
					searchTerm.trim().length >= 2 ? el( 'div', {},
						el( Button, { isSmall: true, disabled: busy || isSaving || searching || searchPage === 1, onClick: function() { setSearchPage( searchPage - 1 ); } }, __( 'Previous FAQs', 'ultimate-faqs' ) ),
						el( Button, { isSmall: true, disabled: busy || isSaving || searching || ! hasMore, onClick: function() { setSearchPage( searchPage + 1 ); } }, __( 'Next FAQs', 'ultimate-faqs' ) ) ) : null
				) : null,
				el( 'div', { className: 'ewd-ufaq-managed-create' },
					el( 'h4', {}, __( 'Create a new FAQ', 'ultimate-faqs' ) ),
					el( TextControl, { label: __( 'Question', 'ultimate-faqs' ), value: createQuestion, onChange: setCreateQuestion } ),
					el( TextareaControl, { label: __( 'Answer', 'ultimate-faqs' ), value: createAnswer, onChange: setCreateAnswer } ),
					config.canAssignCategories ? el( CategorySelector, { disabled: busy || isSaving, onSelect: function( category ) {
						if ( categories.length < 50 && ! categories.some( function( selectedCategory ) { return selectedCategory.id === category.id; } ) ) { setCategories( categories.concat( [ category ] ) ); }
					} } ) : null,
					categories.map( function( category ) { return el( Button, { key: category.id, isSmall: true, disabled: busy || isSaving, onClick: function() { setCategories( categories.filter( function( selectedCategory ) { return selectedCategory.id !== category.id; } ) ); } }, __( 'Remove category:', 'ultimate-faqs' ) + ' ' + category.label ); } ),
					el( 'div', { className: 'ewd-ufaq-managed-create-submit' },
						el( Button, { isPrimary: true, disabled: busy || isSaving || ! config.pagePostId || ! createQuestion.trim(), onClick: createFaq }, busy ? __( 'Working…', 'ultimate-faqs' ) : __( 'Create FAQ', 'ultimate-faqs' ) ) )
				)
			) : null,
			trashId ? el( Modal, { title: __( 'Move FAQ to Trash', 'ultimate-faqs' ), onRequestClose: function() { if ( ! busy ) { setTrashId( null ); } }, shouldCloseOnClickOutside: ! busy },
				el( 'p', {}, __( 'Move this reusable FAQ to Trash? It may disappear from other pages or displays that use it. Removing it from this block only does not Trash the FAQ.', 'ultimate-faqs' ) ),
				el( 'div', { className: 'ewd-ufaq-managed-trash-actions' },
					el( Button, { isSecondary: true, disabled: busy || isSaving, onClick: function() { setTrashId( null ); } }, __( 'Cancel', 'ultimate-faqs' ) ),
					el( Button, { isPrimary: true, isDestructive: true, disabled: busy || isSaving, onClick: trashFaq }, __( 'Confirm move to Trash', 'ultimate-faqs' ) ) )
			) : null
		);
	}

	window.ewdUfaqManagedEditor = ManagedEditor;
	window.ewdUfaqCategorySelector = CategorySelector;
} )( window, window.wp );
