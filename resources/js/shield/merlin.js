(function ( $ ) {
	let $merlinContainer;
	let $merlin;

	$( document ).ready( function () {
		init();
	} );

	function init() {
		$merlinContainer = $( '#merlin' );
		$merlin = $merlinContainer.smartWizard( merlin.vars.smartwizard_cfg );

		$( 'form.merlin-form.ajax-form', $merlinContainer ).on( 'submit', runSettingUpdate );

		$merlinContainer.on( 'click', 'a.skip-step', function () {
			$merlin.smartWizard( 'next' );
		} );
		$( document ).on( 'shield-merlin_save', function ( evt, resp ) {
			if ( resp.success ) {
				$merlin.smartWizard( 'next' );
			}
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