<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1200() {
		( new Lib\Ops\ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();
	}
}