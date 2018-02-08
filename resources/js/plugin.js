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

var iCWP_WPSF_OptionsFormSubmit = new function () {

	var bRequestCurrentlyRunning = false;

	this.submit = function ( sMessage, bSuccess ) {
		var $oDiv = createDynDiv( bSuccess ? 'success' : 'failed' );
		$oDiv.fadeIn().html( sMessage );
		setTimeout( function () {
			$oDiv.fadeOut( 5000 );
			$oDiv.remove();
		}, 4000 );
	};

	/**
	 */
	var submitOptionsForm = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

		if ( bRequestCurrentlyRunning ) {
			return false;
		}

		bRequestCurrentlyRunning = true;

		event.preventDefault();

		var $oForm = jQuery( this );
		jQuery( '<input />' ).attr( 'type', 'hidden' )
							 .attr( 'name', 'action' )
							 .attr( 'value', 'icwp_OptionsFormSave' )
							 .appendTo( $oForm );

		jQuery.post( ajaxurl, $oForm.serialize(),
			function ( oResponse ) {
				var sMessage;
				if ( oResponse.data.message === undefined ) {
					sMessage = oResponse.success ? 'Success' : 'Failure';
				}
				else {
					sMessage = oResponse.data.message;
				}
				jQuery( 'div#icwpOptionsFormContainer' ).html( oResponse.data.options_form )
				iCWP_WPSF_Growl.showMessage( sMessage, oResponse.success );
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", "form.icwpOptionsForm", submitOptionsForm );
		} );
	};
}();

iCWP_WPSF_OptionsPages.initialise();
iCWP_WPSF_OptionsFormSubmit.initialise();