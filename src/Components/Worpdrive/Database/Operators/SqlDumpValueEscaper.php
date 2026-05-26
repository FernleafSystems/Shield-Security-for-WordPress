<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

use FernleafSystems\Wordpress\Services\Services;

class SqlDumpValueEscaper {

	public function escape( $value ) :string {
		$wpdb = Services::WpDb()->loadWpdb();

		return "'".$wpdb->remove_placeholder_escape(
			$wpdb->_real_escape( (string)$value )
		)."'";
	}
}
