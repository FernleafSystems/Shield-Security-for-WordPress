<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;

class EasyDigitalDownloads extends Base {

	protected function register() {
		add_action( 'edd_process_register_form', [ $this, 'checkRegister' ] );
	}

	public function checkRegister() {
		if ( $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			\edd_set_error( 'shield-'.PasswordGenerator::Gen( 6, false, true, false ), $this->getErrorMessage() );
		}
	}
}