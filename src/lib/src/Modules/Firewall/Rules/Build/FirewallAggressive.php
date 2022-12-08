<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallAggressive extends BuildFirewallBase {

	public const SLUG = 'shield/firewall_aggressive';
	public const SCAN_CATEGORY = 'aggressive';
}