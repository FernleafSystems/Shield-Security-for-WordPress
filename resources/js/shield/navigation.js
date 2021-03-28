jQuery.fn.icwpWpsfPluginNavigation = function ( options ) {

	let currentMenuClickTarget;

	var handleLoad = function ( evt, response ) {
		document.querySelector( '#apto-PageMainBody' ).innerHTML = response.data.html;

		window.history.replaceState(
			{},
			response.data.page_title,
			response.data.page_url
		);

		let activeLinks = document.querySelectorAll( '#NavSideBar a.nav-link.active' );
		for ( var i = 0; i < activeLinks.length; i++ ) {
			activeLinks[ i ].classList.remove( 'active' );
		}
		currentMenuClickTarget.classList.add( 'active' );

		let parentNav = currentMenuClickTarget.closest( 'ul' ).closest( 'li.nav-item' );
		if ( parentNav !== null ) {
			parentNav.querySelector( 'a.nav-link' ).classList.add( 'active' );
		}

		console.log( currentMenuClickTarget.closest( 'ul' ).closest( 'a.nav-link' ) );
		iCWP_WPSF_BodyOverlay.hide();
	};

	var renderDynamicPageLoad = function ( params ) {
		sendReq( params );
	};

	var sendReq = function ( params ) {
		document.querySelector( '#apto-PageMainBody' ).innerHTML = 'Loading ...';
		shield_vars_navigation.ajax.dynamic_load.load_params = params;
		iCWP_WPSF_StandardAjax.send_ajax_req(
			shield_vars_navigation.ajax.dynamic_load, 'dynamic_load'
		);
	};

	var initialise = function () {

		jQuery( document ).ready( function () {

			jQuery( document ).on( 'click', 'li.dynamic_body_load > a', function ( evt ) {
				evt.preventDefault();
				currentMenuClickTarget = evt.currentTarget;
				renderDynamicPageLoad( jQuery( currentMenuClickTarget ).data() );
				return false;
			} );

			jQuery( document ).on( 'dynamic_load', handleLoad );
		} );
	};

	var opts = jQuery.extend( {}, options );
	initialise();

	return this;
};