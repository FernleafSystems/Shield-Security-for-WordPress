<?php
/** @var string[] $strings */
/** @var string[] $vars */
/** @var string[] $ajax_vars */
/** @var array $aLicKeyInput */
$aLicKeyInput = $inputs[ 'license_key' ];
?>
<style>
</style>

<script type="text/javascript">
var iCWP_WPSF_LicenseHandler = new function () {

	var bRequestCurrentlyRunning = false;

	/**
	 */
	var submitLicenseForm = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

		if ( bRequestCurrentlyRunning ) {
			return false;
		}
		bRequestCurrentlyRunning = true;

		event.preventDefault();

		var $oForm = jQuery( this );
		jQuery( '<input />' ).attr( 'type', 'hidden' ).attr( 'name', 'action' )
							 .attr( 'value', "<?php echo $ajax_vars[ 'icwp_ajax_action' ]; ?>" )
							 .appendTo( $oForm );
		jQuery( '<input />' ).attr( 'type', 'hidden' ).attr( 'name', 'icwp_ajax_action' )
							 .attr( 'value', "<?php echo $ajax_vars[ 'icwp_ajax_action' ]; ?>" )
							 .appendTo( $oForm );
		jQuery( '<input />' ).attr( 'type', 'hidden' ).attr( 'name', 'icwp_nonce' )
							 .attr( 'value', "<?php echo $ajax_vars[ 'icwp_nonce' ]; ?>" )
							 .appendTo( $oForm );
		jQuery( '<input />' ).attr( 'type', 'hidden' ).attr( 'name', 'icwp_nonce_action' )
							 .attr( 'value', "<?php echo $ajax_vars[ 'icwp_nonce_action' ]; ?>" )
							 .appendTo( $oForm );
		jQuery( '<input />' ).attr( 'type', 'hidden' ).attr( 'name', 'icwp_action_module' )
							 .attr( 'value', "<?php echo $ajax_vars[ 'icwp_action_module' ]; ?>" )
							 .appendTo( $oForm );

		jQuery.post( ajaxurl, $oForm.serialize(),
			function ( oResponse ) {
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
//				iCWP_WPSF_BodyOverlay.hide();
				location.reload( true );
			}
		);
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", "form.licenseForm", submitLicenseForm );
		} );
	};
}();

iCWP_WPSF_LicenseHandler.initialise();
</script>

<div class="bootstrap-wpadmin">
<div class="row-fluid">
	<div class="span12 well">
		<h1>License Summary</h1>
		<table style="width: 600px;">
			<tr>
				<td><?php echo $strings[ 'product_name' ]; ?>:</td>
				<td><?php echo $vars[ 'product_name' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'license_key' ]; ?>:</td>
				<td><?php echo $vars[ 'license_key' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'license_active' ]; ?>:</td>
				<td><?php echo $vars[ 'license_active' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'license_status' ]; ?>:</td>
				<td><?php echo $vars[ 'license_status' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'license_expires' ]; ?>:</td>
				<td><?php echo $vars[ 'license_expires' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'license_email' ]; ?>:</td>
				<td><?php echo $vars[ 'license_email' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'last_checked' ]; ?>:</td>
				<td><?php echo $vars[ 'last_checked' ]; ?></td>
			</tr>
			<tr>
				<td><?php echo $strings[ 'last_errors' ]; ?>:</td>
				<td style="color: #b80000"><?php echo $vars[ 'last_errors' ]; ?></td>
			</tr>
		</table>
	</div>
</div>

<h1>License Actions</h1>
<div class="row-fluid">
	<div class="span12 well">
		<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal licenseForm">
			<input type="hidden" name="license-action" value="recheck" />
			<div class="control-group">
				<label class="control-label">
					Recheck Key
				</label>
				<div class="controls">
					<button class="btn" type="submit"
						<?php echo $flags[ 'button_enabled_remove' ] ? '' : 'disabled="disabled"'; ?> >
						Recheck</button>
					<br />
					<span class="help-inline">Use this to verify and refresh the current license registration.</span>
				</div>
			</div>
		</form>
	</div>
</div>
<div class="row-fluid">
	<div class="span12 well">
		<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal licenseForm">
			<input type="hidden" name="license-action" value="activate" />
			<div class="control-group">
				<label class="control-label" for="<?php echo $aLicKeyInput[ 'name' ]; ?>">
					Activate New License Key
				</label>
				<div class="controls">
					<input type="text" name="<?php echo $aLicKeyInput[ 'name' ]; ?>"
						   id="<?php echo $aLicKeyInput[ 'name' ]; ?>"
						   maxlength="<?php echo $aLicKeyInput[ 'maxlength' ]; ?>"
						   value=""
					/>
					<br /><button class="btn" type="submit" name="activate">Activate Key</button>
					<br /><span class="help-inline">This will replace the current license key.</span>
				</div>
			</div>
		</form>
	</div>
</div>
<div class="row-fluid">
	<div class="span12 well">
		<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal licenseForm">
			<input type="hidden" name="license-action" value="remove" />
			<div class="control-group">
				<label class="control-label">
					Remove Current License
				</label>
				<div class="controls">
					<button class="btn" type="submit"
						<?php echo $flags[ 'button_enabled_remove' ] ? '' : 'disabled="disabled"'; ?> >
						Remove
					</button>
					<br /><span class="help-inline">Important: This will remove all Shield Security Pro features.</span>
				</div>
			</div>
		</form>
	</div>
</div>
</div>