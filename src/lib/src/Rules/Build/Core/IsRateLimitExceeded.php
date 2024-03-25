<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum\EnumLogic,
	Responses
};

class IsRateLimitExceeded extends BuildRuleCoreShieldBase {

	use ModConsumer;

	public const SLUG = 'shield/is_rate_limit_exceeded';

	protected function getName() :string {
		return 'Rate Limit Exceeded';
	}

	protected function getDescription() :string {
		return 'Is traffic rate limit exceeded.';
	}

	protected function getConditions() :array {
		if ( self::con()->comps === null ) {
			$count = $this->opts()->getLimitRequestCount();
			$span = $this->opts()->getLimitTimeSpan();
		}
		else {
			$count = self::con()->opts->optGet( 'limit_requests' );
			$span = self::con()->opts->optGet( 'limit_time_span' );
		}
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsLoggedInNormal::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => Conditions\ShieldConfigIsTrafficRateLimitingEnabled::class,
				],
				[
					'conditions' => Conditions\IsRateLimitExceeded::class,
					'params'     => [
						'limit_count'     => $count,
						'limit_time_span' => $span,
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'request_limit_exceeded',
					'offense_count'    => 1,
					'block'            => false,
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}