<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * Class ProfileBuilder
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders
 * https://wordpress.org/plugins/profile-builder/
 */
class ProfileBuilder extends BaseFormProvider {

	protected function register() {
		add_action( 'wppb_form_before_submit_button', [ $this, 'formInsertsPrint' ], 100 );
		add_filter( 'wppb_output_field_errors_filter', [ $this, 'checkRegister' ], 100 );
	}

	/**
	 * @param array $aErrors
	 * @return array
	 */
	public function checkRegister( $aErrors ) {
		try {
			$this->setActionToAudit( 'profilebuilder-register' )
				 ->checkProviders();
		}
		catch ( \Exception $oE ) {
			$aErrors[ 'shield-fail-register' ] =
				'<span class="wppb-form-error">Bot</span>';
		}
		return $aErrors;
	}
}