<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class PaidMemberSubscriptions extends Base {

	protected function register() {
		add_filter( 'pms_register_form_validation', [ $this, 'checkRegister_PMS' ], 100 );
	}

	public function checkRegister_PMS() {
		if ( $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			\pms_errors()->add( 'shield-fail-register', $this->getErrorMessage() );
		}
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \function_exists( '\pms_errors' );
	}
}