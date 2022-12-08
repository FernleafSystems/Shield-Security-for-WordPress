<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallFieldTruncation extends BuildFirewallBase {

	public const SLUG = 'shield/firewall_field_truncation';
	public const SCAN_CATEGORY = 'field_truncation';
}