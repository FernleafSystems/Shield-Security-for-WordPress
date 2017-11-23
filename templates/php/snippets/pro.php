<?php
/** @var string[] $strings */
/** @var string[] $inputs */
/** @var string[] $flags */
/** @var string[] $vars */
/** @var string[] $ajax_vars */
/** @var array $aLicKeyInput */
$aLicKeyInput = $inputs[ 'license_key' ];
?>
<style>
	#accordionTop .accordion-toggle h3 {
		color: #333333;
	}

	#accordionTop .accordion-toggle {
		background-color: rgba(255, 255, 255, 0.6);
	}

	#accordionTop .accordion-toggle:hover {
		text-decoration: none;
		background-color: rgba(255, 255, 255, 0.6);
	}

	#accordionTop .accordion-toggle:focus {
		box-shadow: none;
	}

	#accordionTop .accordion-group {
		margin-bottom: 15px;
	}

	#accordionTop .accordion-inner {
		background-color: rgba(255, 255, 255, 0.6);
	}

	#ButtonBuyNow {
		margin: 20px;
	}

	#ButtonActivate {
		margin: 5px 2px 0;
	}

</style>

<div class="row-fluid">
	<div class="span12">

		<div class="accordion" id="accordionTop">

			<div class="accordion-group">
				<div class="accordion-heading">
					<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordionTop" href="#collapse1">
						<h3>&rarr; What does Shield Security Pro offer?</h3>
					</a>
				</div>
				<div id="collapse1" class="accordion-body collapse">
					<div class="accordion-inner">
						<dl>
							<dt>Exclusive Customer Support</dt>
							<dd>We are now offering technical support for Shield Security exclusively to premium customers.
								<br />- Why? Unlimited, free support isn't sustainable. To continue delivering our high
								quality plugin and services, we need to a means to support it in a sustainable way.
							</dd>

							<dt>Support for 3rd party plugins</dt>
							<dd>Providing interoperability with 3rd party plugins is a full-time job in itself. Every
								plugin is different. With our first Pro release, we now support Login Protection for
								WooCommerce and Easy Digital Downloads.
								<br />More to come!
							</dd>

							<dt>Exclusive Early-Access to new features </dt>
							<dd>Our position has always been to never restrict access to security features with
								a pro version. This hasn't changed. But, we'll be rolling out our new security features to
								premium customers first, before the free tier.
							</dd>

							<dt>Ability to customize text shown to visitors</dt>
							<dd>You will now be able to edit much of the text of the Shield plugin shown to visitors.</dd>
						</dl>
					</div>
				</div>
			</div>

			<div class="accordion-group">
				<div class="accordion-heading">
					<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordionTop" href="#collapse2">
						<h3>&rarr; Coming Soon...</h3>
					</a>
				</div>
				<div id="collapse2" class="accordion-body collapse">
					<div class="accordion-inner">
						<dl>
							<dt>Import and Export of plugin options</dt>
							<dd>With a release due out in early December you'll be able to import plugin options directly
								from1 site to another.</dd>

							<dt>Select individual plugins for automatic updates</dt>
							<dd>You'll soon be able to select individual plugins for automatic updates. Right now it's all or nothing.</dd>

							<dt>Improved performance/optimizations for PHP 5.6+</dt>
							<dd>We're rewriting your favourite security plugin to take full advantage of the latest PHP developments.</dd>

							<dt>Improved Audit Trail logging and display</dt>
							<dd>The audit trail is a little limited and we plan to rebuild this feature to be far more powerful,
								usable, searchable than ever.</dd>

							<dt>Statistic and Reporting</dt>
							<dd>You'll be able to review and generate reports on keys security events on your sites.</dd>

							<dt>And Much More...</dt>
							<dd>With your support, we'll continue to add more and more features...</dd>
						</dl>
					</div>
				</div>
			</div>

			<div class="accordion-group">
				<div class="accordion-heading">
					<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordionTop" href="#collapse3">
						<h3>&rarr; How To Get Shield Pro?</h3>
					</a>
				</div>
				<div id="collapse3" class="accordion-body collapse in">
					<div class="accordion-inner">
						<p>One Dollar Plugin is our new Plugin Store. We're building a collection of high-quality,
						   highly reliable WordPress plugins for a fraction of the normal cost - making premium WordPress
						   plugins available to everyone.
						</p>
						<p>Shield Pro is our 1st One Dollar Plugin and is sold for the equivalent of $1/month per site.($12/year)</p>
						<ol>
							<li>Just grab a new license from the
								<a href="http://icwp.io/buyshieldpro" target="_blank">One Dollar Plugin here</a>.</li>
							<li>Activate your license on your sites using the Activate button (below).</li>
							<li><strong>No manual premium download</strong> - we automatically take care of it all for you! :)</li>
						</ol>

						<p style="text-align: center">
							<a href="http://icwp.io/buyshieldpro" target="_blank" id="ButtonBuyNow"
							   class="btn btn-large btn-success">
								Buy Shield Security Pro Now &rarr;</a>
						</p>
					</div>
				</div>
			</div>

		</div>

	</div>
</div>
<hr />
<h2>License Summary</h2>
<div class="row-fluid">
	<div class="span12 well">
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

<h2>License Actions</h2>
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
					<br /><button class="btn btn-success" type="submit" id="ButtonActivate" name="activate">Activate Key</button>
					<br /><span class="help-inline">This will replace any existing license key.</span>
				</div>
			</div>
		</form>
	</div>
</div>
<?php if ( $flags[ 'has_license_key' ] ) : ?>
<div class="row-fluid">
	<div class="span12 well">
		<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal licenseForm">
			<input type="hidden" name="license-action" value="recheck" />
			<div class="control-group">
				<label class="control-label">Recheck Key</label>
				<div class="controls">
					<button class="btn btn-info" type="submit"
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
			<input type="hidden" name="license-action" value="remove" />
			<div class="control-group">
				<label class="control-label">
					Remove Current License
				</label>
				<div class="controls">
					<button class="btn btn-warning" type="submit"
						<?php echo $flags[ 'button_enabled_remove' ] ? '' : 'disabled="disabled"'; ?> >
						Remove
					</button>
					<br /><span class="help-inline">Important: This will remove all Shield Security Pro features.</span>
				</div>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

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