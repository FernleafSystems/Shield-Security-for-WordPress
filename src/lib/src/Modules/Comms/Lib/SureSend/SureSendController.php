<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms\Lib\SureSend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SureSendController {

	use ModConsumer;

	public function canUserSend( \WP_User $user ) :bool {
		return Services::WpUsers()->isUserAdmin( $user );
	}

	public function isEnabled2Fa() :bool {
		return $this->isEnabled( '2fa' );
	}

	private function isEnabled( string $slug ) :bool {
		$emails = $this->getOptions()->getOpt( 'suresend_emails' );
		return \in_array( $slug, \is_array( $emails ) ? $emails : [] );
	}
}
