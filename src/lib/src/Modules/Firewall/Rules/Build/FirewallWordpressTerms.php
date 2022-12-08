<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallWordpressTerms extends BuildFirewallBase {

	public const SLUG = 'shield/firewall_wordpress_terms';
	public const SCAN_CATEGORY = 'wordpress_terms';
}