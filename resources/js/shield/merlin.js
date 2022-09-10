(function ( $ ) {
	let $merlinContainer;
	let $merlin;

	$( document ).ready( function () {
		init();
	} );

	function init() {
		$merlinContainer = $( '#merlin' );
		$merlin = $merlinContainer.smartWizard( {
			selected: 0, // Initial selected step, 0 = first step
			theme: 'arrows', // theme for the wizard, related css need to include for other than default theme
			justified: true, // Nav menu justification. true/false
			autoAdjustHeight: true, // Automatically adjust content height
			backButtonSupport: true, // Enable the back button support
			enableUrlHash: true, // Enable selection of the step based on url hash
		} );

		$( 'form', $merlinContainer ).on( 'submit', runSettingUpdate );

		$merlinContainer.on( 'click', 'a.skip-step', function () {
			$merlin.smartWizard( 'next' );
		} );
		$( document ).on( 'shield-merlin_save', function () {
			$merlin.smartWizard( 'next' );
			iCWP_WPSF_BodyOverlay.hide();
		} );
	}

	let runSettingUpdate = function ( evt ) {
		evt.preventDefault();
		merlin.ajax.merlin_action.form_params = $( evt.target ).serialize();
		iCWP_WPSF_StandardAjax.send_ajax_req( merlin.ajax.merlin_action, false, 'merlin_save' );
		return false;
	};

})( jQuery );