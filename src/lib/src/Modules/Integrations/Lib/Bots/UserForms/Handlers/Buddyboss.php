<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class Buddyboss extends Base {

	protected function register() {
		add_action( 'bp_signup_validate', [ $this, 'checkRegister_BP' ] );
	}

	public function checkRegister_BP() {
		if ( $this->setAuditAction( 'register' )->checkIsBot() ) {
			$bp = \buddypress();
			if ( is_object( $bp->signup ) ) {
				$bp->signup->errors[ 'shield-fail-register' ] = 'Failed AntiBot SPAM Check';
			}
		}
	}

	public function getProviderName() :string {
		return 'BuddyBoss';
	}

	public static function IsProviderInstalled() :bool {
		return @class_exists( '\BuddyPress' )
			   && method_exists( '\BuddyPress', 'instance' )
			   && function_exists( '\buddypress' )
			   && \BuddyPress::instance()->buddyboss === true;
	}
}