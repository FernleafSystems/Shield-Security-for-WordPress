<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class SqlQueries extends BaseRequestParams {

	const SLUG = 'sql_queries';

	protected function getScanName() :string {
		return __( 'SQL Queries', 'wp-simple-firewall' );
	}
}