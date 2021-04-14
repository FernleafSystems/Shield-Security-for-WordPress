<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * Class Buddypress
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers
 */
class Buddypress extends Base {

	protected function register() {
		add_action( 'bp_signup_validate', [ $this, 'checkRegister_BP' ], 10 );
	}

	public function checkRegister_BP() {
		if ( $this->setAuditAction( 'edd-register' )->isBot() ) {
			wp_die( $this->getErrorMessage() );
		}
	}

	protected function getProviderName() :string {
		return 'BuddyPress';
	}

	public static function IsHandlerAvailable() :bool {
		return @class_exists( 'BuddyPress' );
	}
}