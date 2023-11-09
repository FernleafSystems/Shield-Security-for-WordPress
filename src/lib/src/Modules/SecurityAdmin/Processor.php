<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		self::con()->getModule_SecAdmin()->getSecurityAdminController()->execute();
		self::con()->getModule_SecAdmin()->getWhiteLabelController()->execute();
	}
}