<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SureSendController {

	use PluginControllerConsumer;

	public function can_2FA( \WP_User $user ) :bool {
		return $this->isEnabled( '2fa' ) && $this->canUserSend( $user );
	}

	public function canUserSend( \WP_User $user ) :bool {
		return Services::WpUsers()->isUserAdmin( $user );
	}

	private function isEnabled( string $slug ) :bool {
		return \in_array( $slug, self::con()->opts->optGet( 'suresend_emails' ) );
	}
}