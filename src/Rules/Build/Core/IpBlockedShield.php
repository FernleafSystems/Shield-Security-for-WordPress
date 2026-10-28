<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\BlockIpAddressShield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\RuleTraits,
	Conditions,
	Responses,
	WPHooksOrder
};

class IpBlockedShield extends BuildRuleIpsBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_ip_blocked_shield';

	protected function getName() :string {
		return 'Is IP Shield Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is Blocked by Shield.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\IsIpBlockedByShield::class,
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\UpdateIpRuleLastAccessAt::class,
			],
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event' => 'conn_kill',
				],
			],
			[
				'response' => Responses\DoAction::class,
				'params'   => [
					'hook' => 'shield/maybe_intercept_block_shield',
				],
			],
			[
				'response' => Responses\DisplayBlockPage::class,
				'params'   => [
					'block_page_slug' => BlockIpAddressShield::SLUG,
				],
			],
		];
	}

	protected function getWpHookLevel() :int {
		return WPHooksOrder::INIT;
	}

	protected function getWpHookPriority() :?int {
		return HookTimings::INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_SHIELD;
	}
}