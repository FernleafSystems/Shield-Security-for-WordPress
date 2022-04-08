<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class BlockRequest extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/is_request_blocked', true );
	}

	protected function run() {
		( new RenderIpBlockPage() )
			->setMod( $this->getMod() )
			->execute();
	}
}