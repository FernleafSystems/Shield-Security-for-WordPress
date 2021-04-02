<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;

/**
 * Class IsScanInQueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class IsScanEnqueued {

	use Databases\Base\HandlerConsumer;

	public function check( string $scan ) :bool {
		/** @var Databases\ScanQueue\Select $selector */
		$selector = $this->getDbHandler()->getQuerySelector();
		return $selector->countForScan( $scan ) > 0;
	}
}
