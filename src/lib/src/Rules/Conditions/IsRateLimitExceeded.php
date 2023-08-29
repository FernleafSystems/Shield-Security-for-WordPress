<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int $limit_count
 * @property int $limit_time_span
 */
class IsRateLimitExceeded extends Conditions\Base {

	use RequestIP;

	public const SLUG = 'is_rate_limit_exceeded';

	protected function execConditionCheck() :bool {
		$ip = $this->getRequestIP();
		$ip = ( new IPRecords() )->loadIP( $ip, false );
		$now = Services::Request()->carbon();
		/** @var Select $selector */
		$selector = self::con()
						->getModule_Data()
						->getDbH_ReqLogs()
						->getQuerySelector();
		$count = $selector->filterByIP( $ip->id )
						  ->filterByCreatedAt( $now->subSeconds( $this->limit_time_span )->timestamp, '>' )
						  ->count();
		$matched = $count > $this->limit_count;

		if ( $matched ) {
			$this->addConditionTriggerMeta( 'request_count', $count );
			$this->addConditionTriggerMeta( 'limit_count', $this->limit_count );
			$this->addConditionTriggerMeta( 'limit_time_span', $this->limit_time_span );
		}

		return $matched;
	}
}