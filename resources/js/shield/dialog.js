var Shield_Dialogs = new function () {

	this.show = function ( $dialog, options ) {
		$dialog.dialog( jQuery.extend( {
			classes: {
				'ui-dialog': 'shield_dialog'
			}
		}, options ) );
	};
}();