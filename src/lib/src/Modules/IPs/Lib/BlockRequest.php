<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages\RenderBlockIP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class BlockRequest extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/is_request_blocked',
			$this->getCon()->this_req->is_ip_blocked );
	}

	protected function run() {
		$this->getCon()->fireEvent( 'conn_kill' );
		( new RenderBlockIP() )
			->setMod( $this->getMod() )
			->display();
	}
}