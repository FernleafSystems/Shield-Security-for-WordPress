<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallSqlQueries extends BuildFirewallBase {

	const SLUG = 'shield/firewall_sql_queries';
	const SCAN_CATEGORY = 'sql_queries';
}