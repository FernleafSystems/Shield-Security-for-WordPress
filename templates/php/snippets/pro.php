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

	.licenseForm button {
		display: block;
	}

</style>

<div class="container-fluid">
	<div class="row content-help">
		<div class="col-5">
			<div class="module-headline">
				<h4>License Summary</h4>
			</div>
			<table class="table table-hover table-sm table-responsive">
				<?php foreach( $vars as $varKey => $licenseValue ) : ?>
					<?php $sClasses = ( $varKey == 'last_errors' && !empty( $licenseValue ) ) ? 'table-warning' : ''; ?>
					<tr>
						<th scope="row"><?php echo $strings[ $varKey ]; ?>:</th>
						<td class="<?php echo $sClasses; ?>"><?php echo $licenseValue; ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<hr />

			<h4>License Actions</h4>
			<div class="row">
				<div class="col card">
					<form action="<?php echo $form_action; ?>" method="post" class="licenseForm">
						<input type="hidden" name="license-action" value="activate" />
						<div class="form-group">
							<label for="<?php echo $aLicKeyInput[ 'name' ]; ?>">Activate New License Key</label>
							<input type="text" name="<?php echo $aLicKeyInput[ 'name' ]; ?>"
								   class="form-control"
								   id="<?php echo $aLicKeyInput[ 'name' ]; ?>"
								   maxlength="<?php echo $aLicKeyInput[ 'maxlength' ]; ?>"
								   value="" />
							<button class="btn btn-success" type="submit" id="ButtonActivate" name="activate">
								Activate Key</button>
							<span class="form-text text-muted">This will replace any existing license key.</span>
						</div>
					</form>
				</div>
			</div>

			<?php if ( $flags[ 'has_license_key' ] ) : ?>
				<div class="row">
					<div class="col card">
						<form action="<?php echo $form_action; ?>" method="post" class="licenseForm">
							<input type="hidden" name="license-action" value="recheck" />
							<div class="form-group">
								<label>Recheck Key</label>
								<button class="btn btn-info" type="submit"
									<?php echo $flags[ 'button_enabled_remove' ] ? '' : 'disabled="disabled"'; ?> >
									Recheck
								</button>
								<span class="form-text text-muted">Verify and refresh the current license registration.</span>
							</div>
						</form>
					</div>
				</div>

				<div class="row">
					<div class="col card">
						<form action="<?php echo $form_action; ?>" method="post" class="licenseForm">
							<input type="hidden" name="license-action" value="remove" />
							<div class="form-group">
								<label>Remove Current License</label>
								<button class="btn btn-warning" type="submit"
									<?php echo $flags[ 'button_enabled_remove' ] ? '' : 'disabled="disabled"'; ?> >
									Remove
								</button>
								<span class="form-text text-muted">Important: This will remove all Shield Security Pro features.</span>
							</div>
						</form>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="col-7">
			<div id="accordion">

				  <div class="card gopro-card">
					<div class="card-header" id="headingOne">
					  <h5 class="mb-0">
						<button class="btn btn-link" data-toggle="collapse" data-target="#collone"
								aria-expanded="true" aria-controls="collone">
						  &rarr; Shield Pro gives you ...
						</button>
					  </h5>
					</div>

					<div id="collone" class="collapse" aria-labelledby="headingOne"
						 data-parent="#accordion">
					  <div class="card-body">
						<dl>
							<dt>Plugin and Theme Vulnerability Scanner</dt>
							<dd>Alerts to plugin/theme vulnerabilities.
								Shield can then automatically upgrade as updates become available.
							</dd>

							<dt>Support for WooCommerce &amp; other 3rd party plugins</dt>
							<dd>Shield Pro works seamlessly with WooCommerce, providing tighter security
								for your customer accounts.
							</dd>

							<dt>Exclusive Customer Support</dt>
							<dd>Technical email support for Shield Security is exclusive to Pro customers.
							</dd>

							<dt>Import and Export of plugin options</dt>
							<dd>Automatically import plugin settings directly from 1 site to another.</dd>

							<dt>Exclusive Early-Access to new features </dt>
							<dd>Be the first to access new security features, as soon as they're available.
							</dd>

							<dt>Unlimited Audit Trail</dt>
							<dd>Retain the audit trail logs for as long as you need - no limits.</dd>

							<dt>Customize text shown to visitors</dt>
							<dd>Edit customer-facing messages/text of the Shield plugin.</dd>
						</dl>
					  </div>
					</div>
				  </div>

				  <div class="card gopro-card">
					<div class="card-header" id="headingTwo">
					  <h5 class="mb-0">
						<button class="btn btn-link collapsed" data-toggle="collapse" data-target="#colltwo"
								aria-expanded="false" aria-controls="colltwo">
						  &rarr; Coming Soon...
						</button>
					  </h5>
					</div>
					<div id="colltwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordion">
					  <div class="card-body">
						<dl>
							<dt>Catch Hacks Immediately - Plugins and Themes Locker</dt>
							<dd>Be alerted immediately to ANY unauthorized changes to plugin and theme files.</dd>

							<dt>White Label</dt>
							<dd>Re-Brand Shield Security as your own!</dd>

							<dt>Select individual plugins for automatic updates</dt>
							<dd>You'll soon be able to select individual plugins for automatic updates. Right now it's all or nothing.</dd>

							<dt>Improved performance/optimizations for PHP 5.6+</dt>
							<dd>We're rewriting your favourite security plugin to take full advantage of the latest PHP developments.</dd>

							<dt>Statistic and Reporting</dt>
							<dd>You'll be able to review and generate reports on keys security events on your sites.</dd>

							<dt>And Much More...</dt>
							<dd>With your continued support, we'll add more and more features...</dd>
						</dl>
					  </div>
					</div>
				  </div>

				  <div class="card gopro-card">
					<div class="card-header" id="headingThree">
					  <h5 class="mb-0">
						<button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collthree"
								aria-expanded="false" aria-controls="collthree">
						  &rarr; How To Get Shield Pro?
						</button>
					  </h5>
					</div>
					<div id="collthree" class="collapse" aria-labelledby="headingThree" data-parent="#accordion">
					  <div class="card-body">

						<p>One Dollar Plugin is our new Plugin Store. We're building a collection of high-quality,
						   highly reliable WordPress plugins for a fraction of the normal cost - making premium WordPress
						   plugins available to everyone.
						</p>
						<p>Shield Pro is our 1st One Dollar Plugin and is sold for the equivalent of $1/month per site.($12/year)</p>
						<ol>
							<li>Just grab a new license from the
								<a href="http://icwp.io/buyshieldpro" target="_blank">One Dollar Plugin here</a>.</li>
							<li>Activate your license on your sites using the 'Activate Key' button.</li>
						</ol>

						<p class="text-center">
							<a href="http://icwp.io/buyshieldpro" target="_blank" id="ButtonBuyNow"
							   class="btn btn-large btn-success">
								Upgrade To Shield Pro Now &rarr;</a>
						</p>
					  </div>
					</div>
				  </div>
				</div>
		</div>

	</div>
</div>
<hr />

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