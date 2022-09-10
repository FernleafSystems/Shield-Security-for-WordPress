<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 16.0
 */
class FirewallLeadingSchema extends BuildFirewallBase {

	const SLUG = 'shield/firewall_leading_schema';
	const SCAN_CATEGORY = 'leading_schema';
}