var iCWP_WPSF_OptionsPages = new function () {

	var showWaiting = function ( event ) {
		/* var $oLink = jQuery( this ); for the inner collapses
		jQuery( '#' + $oLink.data( 'targetcollapse' ) ).collapse( 'show' ); */

		iCWP_WPSF_BodyOverlay.show();
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "click", "a.nav-link.module", showWaiting );
		} );
	};

}();

iCWP_WPSF_OptionsPages.initialise();