var iCWP_WPSF_WhiteLabel = new function () {
	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( 'select#plugin option[value="%s"]' ).remove();
		} );
	};
}();
iCWP_WPSF_WhiteLabel.initialise();