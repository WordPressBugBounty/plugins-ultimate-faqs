var el = wp.element.createElement,
	registerBlockType = wp.blocks.registerBlockType,
	ServerSideRender = wp.serverSideRender,
	TextControl = wp.components.TextControl,
	SelectControl = wp.components.SelectControl,
	BaseControl = wp.components.BaseControl,
	PanelBody = wp.components.PanelBody,
	CheckboxControl = wp.components.CheckboxControl,
	InspectorControls = wp.blockEditor.InspectorControls,
	useEffect = wp.element.useEffect,
	useRef = wp.element.useRef,
	useState = wp.element.useState,
	Localize = wp.i18n.__,
	ewdUfaqBlocks = ewd_ufaq_blocks,
	faqCategories = ewdUfaqBlocks.faqCategoryOptions;

function EwdUfaqEditorPreview( props ) {
	var previewRef = useRef( null );

	useEffect( function() {
		var preview = previewRef.current;
		if ( ! preview ) { return undefined; }

		function bindPreviewInteractions() {
			if ( typeof window.ufaqSetClickHandlers === 'function' ) {
				window.ufaqSetClickHandlers( preview );
			}
		}

		bindPreviewInteractions();
		var PreviewMutationObserver = preview.ownerDocument.defaultView.MutationObserver;
		var observer = new PreviewMutationObserver( bindPreviewInteractions );
		observer.observe( preview, { childList: true, subtree: true } );

		return function() { observer.disconnect(); };
	}, [] );

	return el( 'div', { className: 'ewd-ufaq-editor-preview', ref: previewRef },
		el( ServerSideRender, {
			block: 'ultimate-faqs/ewd-ufaq-display-faq-block',
			attributes: props.attributes
		} )
	);
}

registerBlockType( 'ultimate-faqs/ewd-ufaq-display-faq-block', {
	title: Localize( 'Display FAQs', 'ultimate-faqs' ),
	icon: 'editor-help',
	category: 'ewd-ufaq-blocks',
	attributes: {
		post_count: { 
			type: 'string',
			default: -1
		},
		group_by_category: { type: 'string' },
		faq_accordion: { type: 'string' },
		category_accordion: { type: 'string' },
		include_category_array: { type: 'object', default: {} },
		include_category: { type: 'string' },
		exclude_category: { type: 'string' },
		post__in_string: { type: 'string' },
	},

	edit: function( props ) {

		var include_category_array = props.attributes.include_category_array;
		var hasExplicitFaqIds = String( props.attributes.post__in_string || '' ).trim().length > 0;
		var faqIds = String( props.attributes.post__in_string || '' ).split( ',' ).map( function( id ) { return id.trim(); } ).filter( Boolean );
		var advancedSettingsState = useState( hasExplicitFaqIds );
		var isAdvancedSettingsOpen = advancedSettingsState[0];
		var setAdvancedSettingsOpen = advancedSettingsState[1];
		var previousFaqIds = useRef( faqIds );

		useEffect( function() {
			var faqWasAdded = faqIds.some( function( id ) { return previousFaqIds.current.indexOf( id ) === -1; } );
			if ( faqWasAdded ) { setAdvancedSettingsOpen( true ); }
			previousFaqIds.current = faqIds;
		}, [ props.attributes.post__in_string ] );

		// convert from the old include_category attribute to the new include_category_array attribute
		if ( props.attributes.hasOwnProperty( 'include_category' ) && props.attributes.include_category.length ) { 
			
			var old_categories = props.attributes.include_category.split( ',' );
			
			jQuery( old_categories ).each( function( index, el ) {

				include_category_array[ jQuery.trim( el ) ] = 'true';
			} );
			
			props.setAttributes( { include_category_array: include_category_array } );

			props.setAttributes( { include_category: '' } );
		}

		var checkboxes = faqCategories.map( function( term ) {
			var termLabel = term.label;
			var termSlug = term.slug;
			
			return el( CheckboxControl, {
				label: termLabel,
				checked: include_category_array[termSlug],
				onChange: updateFAQCategories.bind( termSlug ),
			} );
		} );

		function updateFAQCategories( checked ) {
			var copy = Object.assign( {}, include_category_array );
			copy[this] = checked;
			props.setAttributes( { include_category_array: copy } );
		}

		var returnString = [];
		returnString.push(
			el( InspectorControls, {},
				el( TextControl, {
					type: 'number',
					label: Localize( 'Number of FAQs', 'ultimate-faqs' ),
					help: Localize( 'The default of -1 means to display all FAQs.', 'ultimate-faqs' ),
					value: props.attributes.post_count,
					onChange: ( value ) => { props.setAttributes( { post_count: value } ); },
				} ),
				el( SelectControl, {
					label: Localize( 'Group FAQs by Category', 'ultimate-faqs' ),
					value: props.attributes.group_by_category,
					options: [ {value: '', label: 'Default (from settings)'}, {value: 'yes', label: 'Yes'}, {value: 'no', label: 'No'} ],
					onChange: ( value ) => { props.setAttributes( { group_by_category: value } ); },
				} ),
				el( SelectControl, {
					label: Localize( 'FAQ Accordion', 'ultimate-faqs' ),
					value: props.attributes.faq_accordion,
					options: [ {value: '', label: 'Default (from settings)'}, {value: 'yes', label: 'Yes'}, {value: 'no', label: 'No'} ],
					onChange: ( value ) => { props.setAttributes( { faq_accordion: value } ); },
				} ),
				el( SelectControl, {
					label: Localize( 'Category Accordion', 'ultimate-faqs' ),
					value: props.attributes.category_accordion,
					options: [ {value: '', label: 'Default (from settings)'}, {value: 'yes', label: 'Yes'}, {value: 'no', label: 'No'} ],
					onChange: ( value ) => { props.setAttributes( { category_accordion: value } ); },
				} ),
				! hasExplicitFaqIds ? el(
					BaseControl, {
						label: Localize( 'Categories to Include', 'ultimate-faqs' ),
					}, 
					checkboxes
				) : null,
				! hasExplicitFaqIds ? el( TextControl, {
					label: Localize( 'Exclude Category', 'ultimate-faqs' ),
					help: Localize( 'Comma-separated list of category IDs you\'d like to exclude.', 'ultimate-faqs' ),
					value: props.attributes.exclude_category,
					onChange: ( value ) => { props.setAttributes( { exclude_category: value } ); },
				} ) : null,
				el( PanelBody, {
					title: Localize( 'Advanced / legacy settings', 'ultimate-faqs' ),
					opened: isAdvancedSettingsOpen,
					onToggle: setAdvancedSettingsOpen,
				},
					el( TextControl, {
						label: Localize( 'FAQ IDs', 'ultimate-faqs' ),
						help: Localize( 'Comma-separated list of IDs. Use the managed list in the block for normal editing.', 'ultimate-faqs' ),
						value: props.attributes.post__in_string,
						onChange: ( value ) => { props.setAttributes( { post__in_string: value } ); },
					} )
				)
			),
		);
		returnString.push( el( EwdUfaqEditorPreview, props ) );
		if ( window.ewdUfaqManagedEditor ) {
			returnString.push( el( window.ewdUfaqManagedEditor, props ) );
		}
		return returnString;
	},

	save: function() {
		return null;
	},
} );

registerBlockType( 'ultimate-faqs/ewd-ufaq-search-block', {
	title: Localize( 'Search FAQs', 'ultimate-faqs' ),
	icon: 'editor-help',
	category: 'ewd-ufaq-blocks',
	attributes: {
		include_category_array: { type: 'object', default: {} },
		include_category: { type: 'string' },
		exclude_category: { type: 'string' },
		show_on_load: { type: 'string' },
		search_id: { type: 'string' },
	},

	edit: function( props ) {
		if ( ! props.attributes.search_id ) {
			props.setAttributes( { search_id: 'ufaq_search_' + props.clientId.replace( /-/g, '_' ) } );
		}

		var include_category_array = props.attributes.include_category_array;

		// convert from the old include_category attribute to the new include_category_array attribute
		if ( props.attributes.hasOwnProperty( 'include_category' ) && props.attributes.include_category.length ) { 
			
			var old_categories = props.attributes.include_category.split( ',' );
			
			jQuery( old_categories ).each( function( index, el ) {

				include_category_array[ el ] = 'true';
			} );
			
			props.setAttributes( { include_category_array: include_category_array } );

			props.setAttributes( { include_category: '' } );
		}

		var checkboxes = faqCategories.map( function( term ) {
			var termLabel = term.label;
			var termSlug = term.slug;
			
			return el( CheckboxControl, {
				label: termLabel,
				checked: include_category_array[termSlug],
				onChange: updateFAQCategories.bind( termSlug ),
			} );
		} );

		function updateFAQCategories( checked ) {
			var copy = Object.assign( {}, include_category_array );
			copy[this] = checked;
			props.setAttributes( { include_category_array: copy } );
		}

		var returnString = [];
		returnString.push(
			el( InspectorControls, {},
				el(
					BaseControl, {
						label: Localize( 'Categories to Include', 'ultimate-faqs' ),
					}, 
					checkboxes
				),
				el( TextControl, {
					label: Localize( 'Exclude Category', 'ultimate-faqs' ),
					value: props.attributes.exclude_category,
					onChange: ( value ) => { props.setAttributes( { exclude_category: value } ); },
				} ),
				el( SelectControl, {
					label: Localize( 'Show all FAQs on Page Load?', 'ultimate-faqs' ),
					value: props.attributes.show_on_load,
					options: [ {value: '', label: 'No'}, {value: 'Yes', label: 'Yes'} ],
					onChange: ( value ) => { props.setAttributes( { show_on_load: value } ); },
				} )
			),
		);
		returnString.push( el( ServerSideRender, { 
			block: 'ultimate-faqs/ewd-ufaq-search-block',
			attributes: props.attributes
		} ) );
		return returnString;
	},

	save: function() {
		return null;
	},
} );

registerBlockType( 'ultimate-faqs/ewd-ufaq-submit-faq-block', {
	title: Localize( 'Submit FAQ', 'ultimate-faqs' ),
	icon: 'editor-help',
	category: 'ewd-ufaq-blocks',
	attributes: {
	},

	edit: function( props ) {
		var returnString = [];
		returnString.push( el( ServerSideRender, { 
			block: 'ultimate-faqs/ewd-ufaq-submit-faq-block',
		} ) );
		return returnString;
	},

	save: function() {
		return null;
	},
} );

registerBlockType( 'ultimate-faqs/ewd-ufaq-recent-faqs-block', {
	title: Localize( 'Recent FAQs', 'ultimate-faqs' ),
	icon: 'editor-help',
	category: 'ewd-ufaq-blocks',
	attributes: {
		post_count: { 
			type: 'string',
			default: -1
		},
	},

	edit: function( props ) {
		var returnString = [];
		returnString.push(
			el( InspectorControls, {},
				el( TextControl, {
					type: 'number',
					label: Localize( 'Number of FAQs', 'ultimate-faqs' ),
					value: props.attributes.post_count,
					onChange: ( value ) => { props.setAttributes( { post_count: value } ); },
				} )
			),
		);
		returnString.push( el( ServerSideRender, { 
			block: 'ultimate-faqs/ewd-ufaq-recent-faqs-block',
			attributes: props.attributes
		} ) );
		return returnString;
	},

	save: function() {
		return null;
	},
} );

registerBlockType( 'ultimate-faqs/ewd-ufaq-popular-faqs-block', {
	title: Localize( 'Popular FAQs', 'ultimate-faqs' ),
	icon: 'editor-help',
	category: 'ewd-ufaq-blocks',
	attributes: {
		post_count: { 
			type: 'string',
			default: -1
		},
	},

	edit: function( props ) {
		var returnString = [];
		returnString.push(
			el( InspectorControls, {},
				el( TextControl, {
					type: 'number',
					label: Localize( 'Number of FAQs', 'ultimate-faqs' ),
					value: props.attributes.post_count,
					onChange: ( value ) => { props.setAttributes( { post_count: value } ); },
				} )
			),
		);
		returnString.push( el( ServerSideRender, { 
			block: 'ultimate-faqs/ewd-ufaq-popular-faqs-block',
			attributes: props.attributes
		} ) );
		return returnString;
	},

	save: function() {
		return null;
	},
} );


