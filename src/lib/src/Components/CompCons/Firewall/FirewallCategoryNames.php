<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Firewall;

class FirewallCategoryNames {

	public function getFor( string $category ) :string {
		return [
				   'dir_traversal'    => __( 'Directory Traversal', 'wp-simple-firewall' ),
				   'sql_queries'      => __( 'SQL Queries', 'wp-simple-firewall' ),
				   'field_truncation' => __( 'Field Truncation', 'wp-simple-firewall' ),
				   'aggressive'       => __( 'Aggressive Rules', 'wp-simple-firewall' ),
				   'php_code'         => __( 'PHP Code', 'wp-simple-firewall' ),
			   ][ $category ] ?? 'Unspecified';
	}
}