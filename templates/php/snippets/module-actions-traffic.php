<h2 style="margin: 0 0 20px"><?php echo $sTitle; ?></h2>
	<div class="icwpAjaxTableContainer">
		<?php echo $sLiveTrafficTable; ?>
	</div>

<style>
	th.column-created_at {
		width: 130px;
	}
	th.column-path {
		width: 200px;
	}
	th.column-trans {
		width: 130px;
	}
	th.column-code {
		width: 100px;
	}
</style>

<script>

var iCWP_WPSF_AuditTrailTable = new function () {

	var bRequestCurrentlyRunning = false;

	/**
	 */
	var refreshTable = function ( event ) {
		event.preventDefault();

		var $oThis = jQuery( event.currentTarget );
		var $oMainContainer = $oThis.closest( 'div[class="icwpAjaxTableContainer"]' );

		var query = this.search.substring( 1 );
		var aTableRequestParams = {
			paged: extractQueryVars( query, 'paged' ) || 1,
			order: extractQueryVars( query, 'order' ) || 'desc',
			orderby: extractQueryVars( query, 'orderby' ) || 'created_at',
			tableaction: $oThis.data( 'tableaction' )
		};

		sendTableRequest( $oMainContainer, aTableRequestParams );
	};

	var sendTableRequest = function ( $oMainContainer, aTableRequestParams ) {
		if ( bRequestCurrentlyRunning ) {
			return false;
		}
		bRequestCurrentlyRunning = true;

		iCWP_WPSF_BodyOverlay.show();

		var requestData = <?php echo $ajax['render_table']; ?>;

		jQuery.post( ajaxurl, jQuery.extend( aTableRequestParams, requestData ),
			function ( oResponse ) {
				$oMainContainer.html( oResponse.data.html )
			}
		).always(
			function () {
				resetHandlers();
				bRequestCurrentlyRunning = false;
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
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

	var cleanHandlers = function () {
		jQuery( document ).off( "click", 'a.tableActionRefresh' );
	};

	var resetHandlers = function () {
		cleanHandlers();
		setHandlers();
	};

	var setHandlers = function () {
		jQuery( document ).on( "click", 'a.tableActionRefresh', refreshTable );
		jQuery( document ).on( 'click', '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a', refreshTable );

		var timer;
		var delay = 500;
		jQuery( document ).on( 'keyup', 'input[name=paged]', function ( event ) {
			// If user hit enter, we don't want to submit the form
			// We don't preventDefault() for all keys because it would
			// also prevent to get the page number!
			if ( 13 === event.which )
				event.preventDefault();

			// This time we fetch the variables in inputs
			var $oThis = jQuery( event.currentTarget );
			var $oMainContainer = $oThis.closest( 'div[class="icwpAjaxTableContainer"]' );
			var aTableRequestParams = {
				paged: parseInt( jQuery( 'input[name=paged]', $oMainContainer ).val() ) || '1',
				order: jQuery( 'input[name=order]', $oMainContainer ).val() || 'desc',
				orderby: jQuery( 'input[name=orderby]', $oMainContainer ).val() || 'created_at'
			};
			// Now the timer comes to use: we wait half a second after
			// the user stopped typing to actually send the call. If
			// we don't, the keyup event will trigger instantly and
			// thus may cause duplicate calls before sending the intended
			// value
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				sendTableRequest( $oMainContainer, aTableRequestParams );
			}, delay );
		} );
	};

	this.initialise = function () {
		jQuery( document ).ready( setHandlers );
	};
}();

iCWP_WPSF_AuditTrailTable.initialise();

</script>