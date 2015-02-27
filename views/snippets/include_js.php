
<script type="text/javascript">
	jQuery( document ).ready(
		function () {

			/**
			 * Initialise the default states of sections and inputs.
			 */
			jQuery( 'input:checked' ).parents( 'div.option_section' ).addClass( 'active' );

			/**
			 * When the user clicks on a "section", this handler will adjust the radio/checkbox
			 * according to the current value. If the user clicked "section" but actually clicked
			 * on an input field within the section, this normal handler will get called, and the
			 * "section" handler will exit immediately.
			 */
			jQuery( '.option_section' ).on( 'click', onSectionClick );

			/**
			 * When a checkbox, or associated label is clicked, the parent "section" style is updated.
			 */
			jQuery( '.option_section input[type=checkbox],.option_section label' ).on( 'click',
				function ( inoEvent ) {
					var $this = jQuery( this );
					var oParent = $this.parents( 'div.option_section' );

					var oInput = jQuery( 'input[type=checkbox]', oParent );
					if ( oInput.is( ':checked' ) ) {
						oParent.addClass( 'active' );
					}
					else {
						oParent.removeClass( 'active' );
					}
				}
			);

			jQuery( 'select[name=<?php echo $icwp_var_prefix; ?>option]' ).trigger( 'change' );
		}
	);

	function onSectionClick( inoEvent ) {
		/**
		 * Don't manipulate the checkboxes/radios if the actual input or linked-label was
		 * the target of the click.
		 */
		var oDiv = jQuery( inoEvent.currentTarget );
		if ( inoEvent.target.tagName && inoEvent.target.tagName.match( /input|label/i ) ) {
			return true;
		}
				
		var oEl = oDiv.find( 'input[type=checkbox]' );
		if ( oEl.length > 0 ) {
			if ( oEl.is( ':checked' ) ) {
				oEl.removeAttr( 'checked' );
				oDiv.removeClass( 'active' );
			}
			else {
				oEl.attr( 'checked', 'checked' );
				oDiv.addClass( 'active' );
			}
		}

		var oEl = oDiv.find( 'input[type=radio]' );
		if ( oEl.length > 0 && !oEl.is( ':checked' ) ) {
			oEl.attr( 'checked', 'checked' );
			oDiv.addClass( 'active' ).siblings().removeClass( 'active' );
		}

	}
</script>