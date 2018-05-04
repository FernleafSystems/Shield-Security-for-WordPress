<div id="mc_embed_signup">
	<form class="form form-inline validate" action="<?php echo $hrefs[ 'form_action' ]; ?>"
		  method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" target="_blank" novalidate>
		<p><?php echo $strings[ 'summary' ]; ?></p>
		<input type="text" value="" name="EMAIL" class="required email" id="mce-EMAIL"
			   placeholder="<?php echo $strings[ 'your_email' ]; ?>" />
		<input type="text" value="" name="FNAME" class="" id="mce-FNAME"
			   placeholder="<?php echo $strings[ 'your_name' ]; ?>" />
		<input type="hidden" value="<?php echo $install_days; ?>" name="DAYS" class="" id="mce-DAYS" />

		<br />
		<label>
		<input type="checkbox" style="margin:12px 8px" id="OptinConsent" />I certify that I have read and agree to the
			<a href="<?php echo $hrefs[ 'privacy_policy' ]; ?>" target="_blank">Privacy Policy</a>
		</label>
		<br />

		<button type="submit" name="subscribe" id="mc-embedded-subscribe"
				class="button button-primary"><?php echo $strings[ 'yes' ]; ?></button>
		<br /><?php echo $strings[ 'we_dont_spam' ]; ?>
		<div id="mce-responses" class="clear">
			<div class="response" id="mce-error-response" style="display:none"></div>
			<div class="response" id="mce-success-response" style="display:none"></div>
		</div>
		<div style="position: absolute; left: -5000px;"><input type="text" name="b_e736870223389e44fb8915c9a_0e1d527259"
															   tabindex="-1" value=""></div>
		<div class="clear"></div>
	</form>

	<script type="text/javascript">
		jQuery( document ).ready( function ( $ ) {
			var $oSubButton = $( 'form#mc-embedded-subscribe-form button' );
			var $oCheck = $( '#OptinConsent' );
			$oSubButton.attr( "disabled", "disabled" );
			$( document ).on( 'change', $oCheck, function () {
				$oSubButton.prop( "disabled", ! $oCheck.is(":checked") );
			} );
		} );
	</script>
</div>