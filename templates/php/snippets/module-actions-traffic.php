<h2 style="margin: 0 0 20px">
	<?php echo $sTitle; ?>
	<a class="btn btn-light" data-toggle="collapse" href="#collapseFilters" role="button" aria-expanded="false"
	   aria-controls="collapseExample">
    Filter Traffic Log
  </a>
</h2>
<div class="collapse" id="collapseFilters">
	<div class="jumbotron">
		<form id="TrafficFilter">
			<div class="form-group row">
				<div class="col-5">
					<input type="text" id="_fIp" name="fIp" placeholder="IP Address"
						   class="form-control-plaintext" />
				</div>
				<div class="col-5">
					<input type="text" id="_fPage" name="fPage" placeholder="Page/Path Contains..."
						   class="form-control-plaintext" />
				</div>
				<div class="col-2">
					<input type="text" id="_fResponse" name="fResponse" placeholder="Response"
						   class="form-control-plaintext" />
				</div>
			</div>
			<div class="form-group row">
				<div class="col-5">
					<input type="text" id="_fUsername" name="fUsername"
						   placeholder="Username (ignores 'Logged-In' filter)"
						   class="form-control-plaintext" />
				</div>
				<div class="col-3">
					<select id="_fLoggedIn" name="fLoggedIn" class="form-control">
						<option value="-1" selected>Logged-In?</option>
						<option value="1">Yes</option>
						<option value="0">No</option>
					</select>
				</div>
				<div class="col-3">
					<select id="_fTransgression" name="fTransgression" class="form-control">
						<option value="-1" selected>Transgression?</option>
						<option value="1">Yes</option>
						<option value="0">No</option>
					</select>
				</div>
			</div>
			<div class="form-group row">
				<div class="col-4">
					<input type="checkbox" id="_fYou" name="fYou" value="Y"
						   class="form-control" />
					<label class="form-check-label" for="_fYou" title="<?php echo $sYourIp; ?>">
						Exclude Your Current IP?</label>
				</div>
			</div>
			<div class="form-group row">
				<div class="col-6">
					<p>Now click 'Refresh' below...</p>
				</div>
				<div class="offset-4 col-2">
					<a id="ClearForm" href="#" class="btn btn-outline-danger">Clear Filters</a>
				</div>
			</div>
		</form>
		<p></p>
	</div>
</div>

<div class="icwpAjaxTableContainer"><?php echo $sLiveTrafficTable; ?></div>

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

	var iCWP_WPSF_TrafficTable = new function () {

		var bRequestCurrentlyRunning = false;

		/**
		 */
		var refreshTable = function ( event ) {
			event.preventDefault();

			var $oThis = jQuery( event.currentTarget );
			var $oMainContainer = $oThis.closest( 'div[class="icwpAjaxTableContainer"]' );

			var $oForm = jQuery( 'form#TrafficFilter' );

			var query = this.search.substring( 1 );
			var aTableRequestParams = {
				paged: extractQueryVars( query, 'paged' ) || 1,
				order: extractQueryVars( query, 'order' ) || 'desc',
				orderby: extractQueryVars( query, 'orderby' ) || 'created_at',
				tableaction: $oThis.data( 'tableaction' ),
				filters: $oForm.serialize()
			};

			sendTableRequest( $oMainContainer, aTableRequestParams );
		};

		var sendTableRequest = function ( $oMainContainer, aTableRequestParams ) {
			if ( bRequestCurrentlyRunning ) {
				return false;
			}
			bRequestCurrentlyRunning = true;

			iCWP_WPSF_BodyOverlay.show();

			var requestData = <?php echo $ajax[ 'render_table' ]; ?>;

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

	var iCWP_WPSF_TrafficFilters = new function () {
		var resetFilters = function ( event ) {
			var $oForm = jQuery( 'form#TrafficFilter' );
			jQuery( 'input[type=text]', $oForm ).each( function () {
				jQuery( this ).val( '' );
			} );
			jQuery( 'select', $oForm ).each( function () {
				jQuery( this ).prop( 'selectedIndex', 0 );
			} );
			jQuery( 'input[type=checkbox]', $oForm ).each( function () {
				jQuery( this ).prop( 'checked', false );
			} );
		};
		var setHandlers = function () {
			jQuery( document ).on( "click", 'a#ClearForm', resetFilters );
		};
		this.initialise = function () {
			jQuery( document ).ready( setHandlers );
			jQuery( document ).ready( resetFilters );
		};
	}();

	iCWP_WPSF_TrafficTable.initialise();
	iCWP_WPSF_TrafficFilters.initialise();

</script>