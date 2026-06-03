<?php declare( strict_types=1 );

\defined( 'ARRAY_A' ) || \define( 'ARRAY_A', 'ARRAY_A' );
\defined( 'OBJECT' ) || \define( 'OBJECT', 'OBJECT' );

if ( !\class_exists( 'wpdb' ) ) {
	class wpdb {

		public string $base_prefix = 'wp_';

		public string $prefix = 'wp_site_';

		public function parse_db_host( string $host ) :array {
			return [ $host, null, null, false ];
		}
	}
}
