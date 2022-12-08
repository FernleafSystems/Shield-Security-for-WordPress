<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallSqlQueries extends BuildFirewallBase {

	public const SLUG = 'shield/firewall_sql_queries';
	public const SCAN_CATEGORY = 'sql_queries';
}