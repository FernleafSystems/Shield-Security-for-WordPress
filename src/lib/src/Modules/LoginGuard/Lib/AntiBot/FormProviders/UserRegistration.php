<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * TODO: Not ready
 * Class UserRegistration
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders
 * https://wordpress.org/plugins/user-registration/
 */
class UserRegistration extends BaseFormProvider {

	protected function register() {
		add_action( 'user_registration_after_form_fields', [ $this, 'printFormInsert' ], 100 );
		add_action( 'user_registration_response_array', [ $this, 'checkRegister' ], 5, 3 );
	}

	/**
	 * @return void
	 */
	public function printFormInsert() {
		echo '<div class="ur-form-grid">';
		echo preg_replace( '#class="(.*)"#i', 'class="\\1 ur-frontend-field"', $this->buildFormInsert() );
		echo '</div>';
	}

	/**
	 * @param array $response
	 * @param array $aFormData
	 * @param int   $nFormID
	 * @return mixed
	 */
	public function checkRegister( $response, $aFormData, $nFormID ) {
		try {
			$this->setActionToAudit( 'userregistration-register' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			$response[] = $e->getMessage();
		}
		return $response;
	}
}