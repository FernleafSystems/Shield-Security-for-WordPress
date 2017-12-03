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
		if ( bRequestCurrentlyRunning ) { // failsafe in case we balls up - we only run 1.
			return false;
		}

		iCWP_WPSF_BodyOverlay.show();
		bRequestCurrentlyRunning = true;

		event.preventDefault();
		var $oThis = jQuery( event.currentTarget );
		var $oMainContainer = $oThis.closest( 'div[class="icwpAjaxTable"]' );
		var $sAction = $oThis.data( 'tableaction' );

		var requestData = {
			'action': '<?php echo $icwp_ajax_action; ?>',
			'icwp_ajax_action': '<?php echo $icwp_ajax_action; ?>',
			'icwp_nonce': '<?php echo $icwp_nonce; ?>',
			'icwp_nonce_action': '<?php echo $icwp_nonce_action; ?>',
			'icwp_action_module': '<?php echo $icwp_action_module; ?>',
			'auditcontext': $oMainContainer.data( 'auditcontext' ),
			'tableaction': $sAction
		};

		$oMainContainer.html( 'loading...' );
		jQuery.post( ajaxurl, requestData,
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

	var cleanHandlers = function () {
		jQuery( document ).off( "click", 'a.tableActionRefresh' );
	};

	var resetHandlers = function () {
		cleanHandlers();
		setHandlers();
	};

	var setHandlers = function () {
		jQuery( document ).on( "click", 'a.tableActionRefresh', refreshTable );
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