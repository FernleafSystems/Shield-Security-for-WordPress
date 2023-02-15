<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallDirTraversal extends BuildFirewallBase {

	public const SLUG = 'shield/firewall_dir_traversal';
	public const SCAN_CATEGORY = 'dir_traversal';
}