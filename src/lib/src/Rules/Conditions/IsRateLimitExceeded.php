<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int $limit_count
 * @property int $limit_time_span
 */
class IsRateLimitExceeded extends Conditions\Base {

	use Traits\TypeShield;

	public const SLUG = 'is_rate_limit_exceeded';

	protected function execConditionCheck() :bool {
		$ip = ( new IPRecords() )->loadIP( $this->req->ip, false );
		/** @var Select $selector */
		$selector = self::con()
						->getModule_Data()
						->getDbH_ReqLogs()
						->getQuerySelector();
		$count = $selector->filterByIP( $ip->id )
						  ->filterByCreatedAt(
							  Services::Request()->carbon()->subSeconds( $this->limit_time_span )->timestamp, '>'
						  )
						  ->count();

		$this->addConditionTriggerMeta( 'requests', $count );
		$this->addConditionTriggerMeta( 'count', $this->limit_count );
		$this->addConditionTriggerMeta( 'span', $this->limit_time_span );

		return $count > $this->limit_count;
	}

	public function getDescription() :string {
		return __( 'Does the request exceed any traffic rate limits.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'limit_count'     => [
				'type'  => EnumParameters::TYPE_INT,
				'label' => __( 'Request Limit Count', 'wp-simple-firewall' ),
			],
			'limit_time_span' => [
				'type'  => EnumParameters::TYPE_INT,
				'label' => sprintf( '%s (%s)', __( 'Request Limit Interval', 'wp-simple-firewall' ), __( 'seconds' ) ),
			],
		];
	}
}