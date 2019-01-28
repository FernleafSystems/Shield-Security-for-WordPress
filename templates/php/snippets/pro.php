<?php
/** @var string[] $strings */
/** @var string[] $inputs */
/** @var string[] $flags */
/** @var string[] $vars */
/** @var string[] $aLicenseAjax */
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
				<?php foreach ( $vars[ 'license_table' ] as $varKey => $licenseValue ) : ?>
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
					<form method="post" class="licenseForm">
						<?php foreach ( $ajax[ 'license_handling' ] as $sAjKey => $sAjVal ) : ?>
							<input type="hidden" name="<?php echo $sAjKey; ?>" value="<?php echo $sAjVal; ?>" />
						<?php endforeach; ?>
						<input type="hidden" name="license-action" value="check" />
						<div class="form-group">
							<label>Check License Availability For This Site</label>
							<button class="btn btn-info" type="submit"
								<?php echo $flags[ 'button_enabled_check' ] ? '' : 'disabled="disabled"'; ?> >
								Check License
							</button>


							<div class="form-text text-muted">
								<p class="font-weight-bold"><br />Be sure to have first activated your URL in your
								<a target="_blank" href="<?php echo $aHrefs[ 'keyless_cp' ]; ?>">Keyless Activation control panel</a>.</p>
								<ul>
									<li>URL To Activate: <?php echo $vars[ 'activation_url' ]; ?></li>
									<li>Licenses may only be checked once in 20 seconds. Checks more frequent than this will
								automatically be skipped</li>
								</ul>
							</div>
						</div>
					</form>

					<form method="post" id="ConnectionDebug">
						<?php foreach ( $ajax[ 'connection_debug' ] as $sAjKey => $sAjVal ) : ?>
							<input type="hidden" name="<?php echo $sAjKey; ?>" value="<?php echo $sAjVal; ?>" />
						<?php endforeach; ?>
						<button class="btn btn-link btn-sm float-right p-0" type="submit">[Debug]</button>
					</form>
				</div>
			</div>

			<?php if ( false && $flags[ 'has_license_key' ] ) : ?>
				<div class="row">
					<div class="col card">
						<form method="post" class="licenseForm">

							<?php foreach ( $ajax[ 'license_handling' ] as $sAjKey => $sAjVal ) : ?>
								<input type="hidden" name="<?php echo $sAjKey; ?>" value="<?php echo $sAjVal; ?>" />
							<?php endforeach; ?>

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

					<div id="collone" class="collapse show" aria-labelledby="headingOne"
						 data-parent="#accordion">
					  <div class="card-body">
						<dl>
							<dt>Plugin and Theme Vulnerability Scanner</dt>
							<dd>Alerts to plugin/theme vulnerabilities.
								Shield can then automatically upgrade as updates become available.
							</dd>

							<dt>Catch Hacks Immediately - Plugins and Themes Guard</dt>
							<dd>Be alerted to ANY unauthorized changes to plugins/themes.</dd>

							<dt>Powerful User Password Policies</dt>
							<dd>Ensures that all users maintain strong passwords.</dd>

							<dt>Support for WooCommerce &amp; other 3rd party plugins</dt>
							<dd>Provide tighter security for your WooCommerce customers.
							</dd>

							<dt>Exclusive Customer Support</dt>
							<dd>Technical support for Shield is exclusive to Pro customers.</dd>

							<dt>Import and Export of plugin options</dt>
							<dd>Automatically import settings directly from 1 site to another.</dd>

							<dt>Exclusive Early-Access </dt>
							<dd>Be 1st to get new security features, as soon as they're available.</dd>

							<dt>Unlimited Audit Trail</dt>
							<dd>Retain logs for as long as you need - no limits.</dd>

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
						<p>Shield Pro is our first One Dollar Plugin and is sold for the equivalent of
						   $1/month per site ($12/year)</p>
						<ol>
							<li>Just grab a new license from the
								<a href="https://icwp.io/buyshieldpro" target="_blank">One Dollar Plugin here</a>.</li>
							<li>Activate your license on your sites using the 'Activate Key' button.</li>
						</ol>

						<p class="text-center">
							<a href="https://icwp.io/buyshieldpro" target="_blank" id="ButtonBuyNow"
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
		jQuery.post( ajaxurl, $oForm.serialize(),
			function ( oResponse ) {
				if ( typeof oResponse !== 'undefined' && typeof oResponse.data !== 'undefined' ) {
					iCWP_WPSF_Toaster.showMessage( oResponse.data.message, oResponse.data.success );
					// iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.data.success );
				}
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
				// iCWP_WPSF_BodyOverlay.hide();
				setTimeout( function () {
					location.reload( true );
				}, 2000 );
			}
		);
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", "form.licenseForm", submitLicenseForm );
		} );
	};
}();

var iCWP_WPSF_ConnectionDebug = new function () {
	/**
	 */
	var connectionDebug = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();
		event.preventDefault();

		var $oForm = jQuery( this );
		jQuery.post( ajaxurl, $oForm.serialize(),
			function ( oResponse ) {
				alert( oResponse.data.message );
			}
		).always( function () {
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", "form#ConnectionDebug", connectionDebug );
		} );
	};
}();

iCWP_WPSF_LicenseHandler.initialise();
iCWP_WPSF_ConnectionDebug.initialise();
</script>