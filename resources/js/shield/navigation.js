jQuery.fn.icwpWpsfPluginNavigation = function ( options ) {

	let currentMenuLoadItem;

	var handleDynamicLoad = function ( evt, response ) {
		document.querySelector( '#apto-PageMainBody' ).innerHTML = response.data.html;

		let urlHash = window.location.hash ? window.location.hash : '';
		// Using links to specific config sections, we extract the section and trigger the tab show()
		if ( urlHash ) {
			let theTabToShow = document.querySelector( '#tab-navlink-' + urlHash.split( '-' )[ 1 ] );
			if ( theTabToShow ) {
				(new bootstrap.Tab( theTabToShow )).show();
			}
		}
		jQuery( 'html,body' ).scrollTop( 0 );

		/**
		 *  we then update the window URL (only after triggering tabs)
		 *  We need to take into account the window's hash link. We do this by checking
		 *  for its existence on-page and only add it back to the URL if it's applicable.
		 */
		let replaceStateUrl = response.data.page_url;
		let hashOnPageElement = document.getElementById( urlHash.replace( /#/, '' ) );
		if ( hashOnPageElement ) {
			replaceStateUrl += urlHash;
		}
		window.history.replaceState(
			{},
			response.data.page_title,
			replaceStateUrl
		);

		document.getElementById( 'PageTitle' ).innerHTML = response.data.page_title;

		let activeLinks = document.querySelectorAll( '#NavSideBar a.nav-link.active' );
		for ( let i = 0; i < activeLinks.length; i++ ) {
			activeLinks[ i ].classList.remove( 'active' );
		}
		currentMenuLoadItem.classList.add( 'active' );

		let parentNav = currentMenuLoadItem.closest( 'ul' ).closest( 'li.nav-item' );
		if ( parentNav !== null ) {
			parentNav.querySelector( 'li.nav-item > a.nav-link' ).classList.add( 'active' );
		}

		iCWP_WPSF_BodyOverlay.hide();
	};

	let renderDynamicPageLoad = function ( params ) {
		document.querySelector( '#apto-PageMainBody' ).innerHTML = '<div class="d-flex justify-content-center align-items-center h-100"><div class="spinner-border text-success m-5" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		shield_vars_navigation.ajax.dynamic_load.dynamic_load_params = params;
		iCWP_WPSF_StandardAjax.send_ajax_req(
			shield_vars_navigation.ajax.dynamic_load, false, 'dynamic_load'
		);
	};

	var initialise = function () {

		jQuery( document ).on( 'shield-dynamic_load', handleDynamicLoad );

		jQuery( document ).ready( function () {

			jQuery( document ).on( 'click', 'a.dynamic_body_load', function ( evt ) {
				evt.preventDefault();
				currentMenuLoadItem = evt.currentTarget;
				renderDynamicPageLoad( jQuery( currentMenuLoadItem ).data( 'dynamic_page_load' ) );
				return false;
			} );

			let activePageLink = jQuery( '#NavSideBar a.active.body_content_link.dynamic_body_load' );
			if ( activePageLink.length === 1 ) {
				currentMenuLoadItem = activePageLink[ 0 ];
				renderDynamicPageLoad( jQuery( activePageLink ).data( 'dynamic_page_load' ) );
			}
		} );
	};

	let opts = jQuery.extend( {}, options );
	initialise();

	return this;
};