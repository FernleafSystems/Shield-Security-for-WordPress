<script type="text/javascript">

	var iCWP_WPSF_Recaptcha = new function () {

		this.setupForm = function ( form ) {

			var recaptchaContainer = form.querySelector('.icwpg-recaptcha');

			if ( recaptchaContainer !== null ) {

				var recaptchaContainerSpec = grecaptcha.render(
					recaptchaContainer,
					{
						'sitekey': '<?php echo $sitekey;?>',
						'size': '<?php echo $size;?>',
						'theme': '<?php echo $theme;?>',
						'badge': 'bottomright',
						'callback' : function ( reCaptchaToken ) {
							<?php if ( $invis ) : ?>
							HTMLFormElement.prototype.submit.call( form );
							<?php endif;?>
						},
						'expired-callback' : function() {
							grecaptcha.reset( recaptchaContainerSpec );
						}
					}
				);

				jQuery( 'input[type=submit]', form ).on( 'click', function( event ) {
					<?php if ( $invis ) : ?>
					event.preventDefault();
					grecaptcha.execute( recaptchaContainerSpec );
					<?php endif;?>
				});
			}
		};

		this.initialise = function () {
			if ( grecaptcha !== undefined ) {
				for ( var i = 0; i < document.forms.length; i++ ) {
					this.setupForm( document.forms[ i ] );
				}
			}
		};
	}();

	var onLoadIcwpRecaptchaCallback = function() {
		iCWP_WPSF_Recaptcha.initialise();
	};
</script>