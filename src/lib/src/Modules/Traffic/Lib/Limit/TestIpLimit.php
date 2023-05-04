<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Services\Services;

class TestIpLimit {

	use Shield\Modules\ModConsumer;
	use Shield\Modules\IPs\Components\IpAddressConsumer;

	/**
	 * returns true if request is allowed (i.e. request limit has not been exceeded)
	 * @throws \Exception|RateLimitExceededException
	 */
	public function run() :bool {
		/** @var Shield\Modules\Traffic\Options $opts */
		$opts = $this->getOptions();

		if ( !empty( $this->getIP() ) ) {
			$ip = ( new IPRecords() )->loadIP( $this->getIP(), false );
			$now = Services::Request()->carbon();
			/** @var ReqLogs\Ops\Select $selector */
			$selector = $this->con()
							 ->getModule_Data()
							 ->getDbH_ReqLogs()
							 ->getQuerySelector();
			$count = $selector->filterByIP( $ip->id )
							  ->filterByCreatedAt( $now->subSeconds( $opts->getLimitTimeSpan() )->timestamp, '>' )
							  ->count();
			if ( $count > $opts->getLimitRequestCount() ) {
				throw new RateLimitExceededException( 'Rate limit triggered.', $count );
			}
		}

		return true;
	}
}