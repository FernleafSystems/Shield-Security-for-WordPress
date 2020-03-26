<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * Class PaidMemberSubscriptions
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders
 * https://wordpress.org/plugins/paid-member-subscriptions
 */
class PaidMemberSubscriptions extends BaseFormProvider {

	protected function register() {
		add_action( 'pms_register_form_after_fields', [ $this, 'formInsertsPrint' ], 100 );
		add_filter( 'pms_register_form_validation', [ $this, 'checkRegister' ], 100 );
	}

	public function checkRegister() {
		try {
			$this->setActionToAudit( 'paidmembersubscriptions-register' )
				 ->checkProviders();
		}
		catch ( \Exception $oE ) {
			\pms_errors()->add( 'shield-fail-register', $oE->getMessage() );
		}
	}
}