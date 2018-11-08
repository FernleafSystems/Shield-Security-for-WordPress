jQuery.fn.icwpWpsfTableIps = function ( aOptions ) {

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	/**
	 * @var icwpWpsfAjaxTable
	 */
	var $oTable;

	initialise();

	function initialise() {
		$oTable = jQuery( aOpts[ 'id_table_container' ] ).icwpWpsfAjaxTable( aOpts );
		jQuery( document ).ready( function () {
			$oThis.on( 'submit', 'form', addIpFromFormSubmit );
			$oTable.on( 'click', 'td.column-actions a.delete', deleteEntry );
		} );
	}

	var addIpFromFormSubmit = function ( event ) {
		event.preventDefault();
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery( this ).serialize(),
			function ( oResponse ) {
				if ( oResponse.success ) {
					$oTable.reloadTable();
					iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}
			}
		).always( function () {
			}
		);
	};

	var deleteEntry = function ( event ) {
		event.preventDefault();
		iCWP_WPSF_BodyOverlay.show();

		var requestData = aOpts[ 'ajax_delete_ip' ];
		requestData[ 'id' ] = jQuery( this ).data( 'id' );

		jQuery.post( ajaxurl, requestData,
			function ( oResponse ) {
				if ( oResponse.success ) {
					$oTable.reloadTable();
					iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}
			}
		).always( function () {
			}
		);
	};

	return this;
};