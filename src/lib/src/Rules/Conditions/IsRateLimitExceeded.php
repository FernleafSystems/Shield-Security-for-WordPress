<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	IPs\IPRecords,
	ReqLogs\Ops as ReqLogsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};

class IsRateLimitExceeded extends Conditions\Base {

	use Traits\TypeShield;

	public const SLUG = 'is_rate_limit_exceeded';

	protected function execConditionCheck() :bool {
		$ip = ( new IPRecords() )->loadIP( $this->req->ip, false );

		/** @var ReqLogsDB\Select $selector */
		$selector = self::con()->db_con->req_logs->getQuerySelector();
		$count = $selector->filterByIP( $ip->id )
						  ->filterByCreatedAt(
							  ( clone $this->req->carbon )->subSeconds( $this->p->limit_time_span )->timestamp, '>'
						  )
						  ->count();

		$this->addConditionTriggerMeta( 'requests', $count );
		$this->addConditionTriggerMeta( 'count', $this->p->limit_count );
		$this->addConditionTriggerMeta( 'span', $this->p->limit_time_span );

		return $count > $this->p->limit_count;
	}

	public function getDescription() :string {
		return __( 'Does the request exceed any traffic rate limits.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'limit_count'     => [
				'type'  => Enum\EnumParameters::TYPE_INT,
				'label' => __( 'Request Limit Count', 'wp-simple-firewall' ),
			],
			'limit_time_span' => [
				'type'  => Enum\EnumParameters::TYPE_INT,
				'label' => sprintf( '%s (%s)', __( 'Request Limit Interval', 'wp-simple-firewall' ), __( 'seconds' ) ),
			],
		];
	}
}