<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms\Lib\SureSend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SureSendController {

	use ModConsumer;

	public function can_2FA( \WP_User $user ) :bool {
		return $this->isEnabled( '2fa' ) && $this->canUserSend( $user );
	}

	public function canUserSend( \WP_User $user ) :bool {
		return Services::WpUsers()->isUserAdmin( $user );
	}

	private function isEnabled( string $slug ) :bool {
		$emails = $this->opts()->getOpt( 'suresend_emails' );
		return \in_array( $slug, \is_array( $emails ) ? $emails : [] );
	}
}