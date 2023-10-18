import { ShieldOverlay } from "./util/ShieldOverlay";

jQuery.fn.icwpWpsfAjaxTable = function ( aOptions ) {

	this.reloadTable = function () {
		renderTableRequest();
	};

	var createTableContainer = function () {
		$oTableContainer = jQuery( '<div />' ).appendTo( $oThis );
		$oTableContainer.addClass( 'icwpAjaxTableContainer' );
	};

	var refreshTable = function ( evt ) {
		evt.preventDefault();

		var query = this.search.substring( 1 );
		var aTableRequestParams = {
			paged: extractQueryVars( query, 'paged' ) || 1,
			order: extractQueryVars( query, 'order' ) || 'desc',
			orderby: extractQueryVars( query, 'orderby' ) || 'created_at',
			tableaction: jQuery( evt.currentTarget ).data( 'tableaction' )
		};

		renderTableRequest( aTableRequestParams );
	};

	var extractQueryVars = function ( query, variable ) {
		var vars = query.split( "&" );
		for ( var i = 0; i < vars.length; i++ ) {
			var pair = vars[ i ].split( "=" );
			if ( pair[ 0 ] === variable ) {
				return pair[ 1 ];
			}
		}
		return false;
	};

	this.renderTableFromForm = function ( $oForm ) {
		renderTableRequest( { 'form_params': $oForm.serialize() } );
	};

	var renderTableRequest = function ( aTableRequestParams ) {
		if ( bReqRunning ) {
			return false;
		}
		bReqRunning = true;
		ShieldOverlay.show();

		jQuery.post( ajaxurl, jQuery.extend( aOpts[ 'ajax_render' ], aOpts[ 'req_params' ], aTableRequestParams ),
			function ( oResponse ) {
				$oTableContainer.html( oResponse.data.html )
			}
		).always(
			function () {
				bReqRunning = false;
				shieldServices.overlay().hide();
			}
		);
	};

	var setHandlers = function () {
		$oThis.on( "click", 'a.tableActionRefresh', refreshTable );
		$oThis.on( 'click', '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a', refreshTable );

		var timer;
		var delay = 1000;
		jQuery( document ).on( 'keyup', 'input[name=paged]', function ( event ) {
			// If user hit enter, we don't want to submit the form
			// We don't preventDefault() for all keys because it would
			// also prevent to get the page number!
			if ( 13 === event.which )
				event.preventDefault();

			// This time we fetch the variables in inputs
			var $eThis = jQuery( event.currentTarget );
			var aTableRequestParams = {
				paged: isNaN( $eThis.val() ) ? 1 : $eThis.val(),
				order: jQuery( 'input[name=order]', $eThis ).val() || 'desc',
				orderby: jQuery( 'input[name=orderby]', $eThis ).val() || 'created_at'
			};
			// Now the timer comes to use: we wait a second after
			// the user stopped typing to actually send the call. If
			// we don't, the keyup event will trigger instantly and
			// thus may cause duplicate calls before sending the intended
			// value
			renderTableRequest( aTableRequestParams );
		} );
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			createTableContainer();
			renderTableRequest();
			setHandlers();
		} );
	};

	var $oThis = this;
	var $oTableContainer;
	var bReqRunning = false;
	var aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};

jQuery( 'document' ).ready( function () {

	/** Progress Meters: */
	// (new CircularProgressBar( 'pie' )).initial();
} );