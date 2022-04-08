<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallPhpCode extends BuildFirewallBase {

	const SLUG = 'shield/firewall_php_code';
	const SCAN_CATEGORY = 'php_code';
}