<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;

/**
 * Class IsScanInQueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class IsScanEnqueued {

	use Databases\Base\HandlerConsumer;

	/**
	 * @param string $sScanSlug
	 * @return bool
	 */
	public function check( $sScanSlug ) {
		/** @var Databases\ScanQueue\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
		return $oSel->countForScan( $sScanSlug ) > 0;
	}
}
