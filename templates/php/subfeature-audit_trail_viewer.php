<div class="row">
	<div class="span12" id="AuditTrailTabs">

		<ul class="nav nav-tabs" id="AuditTrailTabs">
		<?php foreach ( $aAuditTables as $sContext => $aAuditDataContext ) : ?>
			<li><a href="#Context<?php echo $sContext; ?>" data-toggle="tab">
					<?php echo $aContexts[ $sContext ]; ?>
				</a></li>
		<?php endforeach; ?>
		</ul>
		<div class="tab-content">
			<?php foreach ( $aAuditTables as $sContext => $aAuditDataContext ) : ?>
				<div class="tab-pane <?php echo !$sContext ? 'active' : '' ?>" id="Context<?php echo $sContext; ?>">
					<div class="icwpAjaxTable"
						 data-auditcontext="<?php echo $sContext; ?>"><?php echo $aAuditDataContext; ?></div>
				</div>
			<?php endforeach; ?>
		</div>

	</div><!-- / span9 -->
</div><!-- / row -->

<script>

var iCWP_WPSF_AuditTrailTable = new function () {

	var bRequestCurrentlyRunning = false;

	/**
	 */
	var refreshTable = function ( event ) {
		event.preventDefault();
		if ( bRequestCurrentlyRunning ) { // failsafe in case we balls up - we only run 1.
			return false;
		}
		bRequestCurrentlyRunning = true;

		event.preventDefault();
		var $oThis = jQuery( event.currentTarget );
		var $oMainContainer = $oThis.closest( 'div[class="icwpAjaxTable"]' );
		var $sAction = $oThis.data( 'tableaction' );

		iCWP_WPSF_BodyOverlay.show();
		$oMainContainer.html( 'loading...' );

		var query = this.search.substring( 1 );
		var filterPagingData = {
			paged: extractQueryVars( query, 'paged' ) || '1',
			order: extractQueryVars( query, 'order' ) || 'desc',
			orderby: extractQueryVars( query, 'orderby' ) || 'created_at'
		};
		var requestData = {
			'action': '<?php echo $icwp_ajax_action; ?>',
			'icwp_ajax_action': '<?php echo $icwp_ajax_action; ?>',
			'icwp_nonce': '<?php echo $icwp_nonce; ?>',
			'icwp_nonce_action': '<?php echo $icwp_nonce_action; ?>',
			'icwp_action_module': '<?php echo $icwp_action_module; ?>',
			'auditcontext': $oMainContainer.data( 'auditcontext' ),
			'tableaction': $sAction
		};

		jQuery.post( ajaxurl, jQuery.extend( filterPagingData, requestData ),
			function ( oResponse ) {
				$oMainContainer.html( oResponse.data.tablecontent )
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
			if ( pair[ 0 ] == variable ) {
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
		jQuery( document ).on( "click", '.tablenav-pages a', refreshTable );
		jQuery( document ).on( "click", '.manage-column.sortable a', refreshTable );
		jQuery( document ).on( "click", '.manage-column.sorted a', refreshTable );

		// $('.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a').on('click', function(e) {
		// 	// We don't want to actually follow these links
		// 	e.preventDefault();
		// 	// Simple way: use the URL to extract our needed variables
		// 	var query = this.search.substring( 1 );
		//
		// 	var data = {
		// 		paged: list.__query( query, 'paged' ) || '1',
		// 		order: list.__query( query, 'order' ) || 'asc',
		// 		orderby: list.__query( query, 'orderby' ) || 'title'
		// 	};
		// 	list.update( data );
		// });
	};

	this.initialise = function () {
		jQuery( document ).ready( setHandlers );
	};
}();

jQuery( function () {
	jQuery( '#AuditTrailTabs > ul a:first' ).tab( 'show' );
} );

iCWP_WPSF_AuditTrailTable.initialise();

</script>