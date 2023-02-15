<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * https://wordpress.org/plugins/profile-builder/
 */
class ProfileBuilder extends BaseFormProvider {

	protected function register() {
		add_action( 'wppb_form_before_submit_button', [ $this, 'printFormInsert' ], 100 );
		add_filter( 'wppb_output_field_errors_filter', [ $this, 'checkRegister' ], 100 );
	}

	/**
	 * @param array $errors
	 * @return array
	 */
	public function checkRegister( $errors ) {
		try {
			$this->setActionToAudit( 'profilebuilder-register' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			$errors[ 'shield-fail-register' ] = '<span class="wppb-form-error">Bot</span>';
		}
		return $errors;
	}
}