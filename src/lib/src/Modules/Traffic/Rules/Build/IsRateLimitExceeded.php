<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class IsRateLimitExceeded extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_rate_limit_exceeded';

	protected function getName() :string {
		return 'Rate Limit Exceeded';
	}

	protected function getDescription() :string {
		return 'Is traffic rate limit exceeded.';
	}

	protected function getConditions() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\IsNotLoggedInNormal::SLUG,
				],
				[
					'condition' => Conditions\IsRateLimitExceeded::SLUG,
					'params'    => [
						'limit_count'     => $opts->getLimitRequestCount(),
						'limit_time_span' => $opts->getLimitTimeSpan(),
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::SLUG,
				'params'   => [
					'event'            => 'request_limit_exceeded',
					'offense_count'    => 1,
					'block'            => false,
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
			[
				'response' => Responses\TrafficRateLimitExceeded::SLUG,
			],
		];
	}

	protected function getCommonAuditParamsMapping() :array {
		return array_merge( parent::getCommonAuditParamsMapping(), [
			'requests' => 'request_count',
			'count'    => 'limit_count',
			'span'     => 'limit_time_span',
		] );
	}
}