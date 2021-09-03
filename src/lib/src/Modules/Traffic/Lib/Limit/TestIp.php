<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Services\Services;

class TestIp {

	use Shield\Modules\ModConsumer;
	use Shield\Modules\IPs\Components\IpAddressConsumer;

	/**
	 * @return bool  - true if request is allowed (i.e. request limit has not been exceeded)
	 * @throws \Exception
	 */
	public function runTest() :bool {
		/** @var Shield\Modules\Traffic\Options $opts */
		$opts = $this->getOptions();

		if ( !empty( $this->getIP() ) ) {
			try {
				$ip = ( new IPRecords() )
					->setMod( $this->getCon()->getModule_Data() )
					->loadIP( $this->getIP(), false );
				$now = Services::Request()->carbon();
				/** @var ReqLogs\Ops\Select $selector */
				$selector = $this->getCon()
								 ->getModule_Data()
								 ->getDbH_ReqLogs()
								 ->getQuerySelector();
				$count = $selector->filterByIP( $ip->id )
								  ->filterByCreatedAt( $now->subSeconds( $opts->getLimitTimeSpan() )->timestamp, '>' )
								  ->count();
				if ( $count > $opts->getLimitRequestCount() ) {
					throw new \Exception( 'Requests from IP have exceeded allowable limit.' );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return true;
	}
}