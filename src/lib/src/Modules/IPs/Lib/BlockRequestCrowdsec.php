<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages\RenderBlockIpCrowdSec;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class BlockRequestCrowdsec extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->getCon()->this_req->is_ip_crowdsec_blocked;
	}

	protected function run() {
		$this->getCon()->fireEvent( 'conn_kill_crowdsec' );
		( new RenderBlockIpCrowdSec() )
			->setMod( $this->getMod() )
			->display();
	}
}