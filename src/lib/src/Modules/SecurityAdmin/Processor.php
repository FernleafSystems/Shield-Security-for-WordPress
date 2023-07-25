<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		self::con()->getModule_SecAdmin()->getSecurityAdminController()->execute();
		self::con()->getModule_SecAdmin()->getWhiteLabelController()->execute();
	}
}