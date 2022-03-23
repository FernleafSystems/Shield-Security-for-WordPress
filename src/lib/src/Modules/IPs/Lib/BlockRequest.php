<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BlockRequest extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/is_request_blocked', $this->getCon()->req->is_ip_blocked );
	}

	protected function run() {
		if ( $this->isAutoUnBlocked() ) {
			Services::Response()->redirectToHome();
		}
		else {
			( new RenderIpBlockPage() )
				->setMod( $this->getMod() )
				->execute();
		}
	}

	private function isAutoUnBlocked() :bool {
		return ( new AutoUnblock() )
			->setMod( $this->getMod() )
			->run();
	}
}