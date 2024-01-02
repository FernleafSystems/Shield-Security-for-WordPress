<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\RuleTraits,
	Conditions,
	Responses
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\BlockIpAddressCrowdsec;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;

class IpBlockedCrowdsec extends BuildRuleIpsBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_ip_blocked_crowdsec';

	protected function getName() :string {
		return 'Is IP CrowdSec Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is on CrowdSec and should be blocked.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\IsIpBlockedCrowdsec::class,
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\UpdateIpRuleLastAccessAt::class,
			],
			[
				'response' => Responses\DoAction::class,
				'params'   => [
					'hook' => 'shield/maybe_intercept_block_crowdsec',
				],
			],
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event' => 'conn_kill_crowdsec',
				],
			],
			[
				'response' => Responses\DisplayBlockPage::class,
				'params'   => [
					'block_page_slug' => BlockIpAddressCrowdsec::SLUG,
					'hook'            => 'init',
					'priority'        => HookTimings::INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_CROWDSEC,
				],
			],
		];
	}
}