<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib;

class FirewallCategoryNames extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Strings {

	public function getFor( string $category ) :string {
		return [
				   'dir_traversal'    => __( 'Directory Traversal', 'wp-simple-firewall' ),
				   'wordpress_terms'  => __( 'WordPress Terms', 'wp-simple-firewall' ),
				   'sql_queries'      => __( 'SQL Queries', 'wp-simple-firewall' ),
				   'field_truncation' => __( 'Field Truncation', 'wp-simple-firewall' ),
				   'aggressive'       => __( 'Aggressive Rules', 'wp-simple-firewall' ),
				   'leading_schema'   => __( 'Leading Schema', 'wp-simple-firewall' ),
				   'php_code'         => __( 'PHP Code', 'wp-simple-firewall' ),
				   'exe_file_uploads' => __( 'EXE File Uploads', 'wp-simple-firewall' ),
			   ][ $category ] ?? 'Unspecified';
	}
}