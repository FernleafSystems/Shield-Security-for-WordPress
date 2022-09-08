<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockVisitor extends BaseAutoUnblockShield {

	protected function canRun() :bool {
		return parent::canRun() && Services::Request()->isPost();
	}

	protected function getUnblockMethodName() :string {
		return 'Visitor Auto-Unblock';
	}

	public function isUnblockAvailable() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledAutoVisitorRecover() && parent::isUnblockAvailable();
	}
}