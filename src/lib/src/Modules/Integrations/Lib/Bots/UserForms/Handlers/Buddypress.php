<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class Buddypress extends Base {

	protected function register() {
		add_action( 'bp_signup_validate', [ $this, 'checkRegister_BP' ] );
	}

	public function checkRegister_BP() {
		if ( $this->setAuditAction( 'register' )->checkIsBot() ) {
			wp_die( $this->getErrorMessage() );
		}
	}

	public static function IsProviderInstalled() :bool {
		return @class_exists( '\BuddyPress' );
	}
}