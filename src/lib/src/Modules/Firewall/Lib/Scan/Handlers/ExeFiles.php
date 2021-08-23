<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class ExeFiles extends Base {

	const SLUG = 'exefile';
	const TYPE = 'file';

	protected function getItemsToScan() :array {
		return array_filter( array_map(
			function ( $file ) {
				return $file[ 'name' ] ?? '';
			},
			( !empty( $_FILES ) && is_array( $_FILES ) ) ? $_FILES : []
		) );
	}

	protected function getScanName() :string {
		return __( 'Exe File', 'wp-simple-firewall' );
	}
}