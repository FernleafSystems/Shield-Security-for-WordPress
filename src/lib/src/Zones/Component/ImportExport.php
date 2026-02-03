<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class ImportExport extends Base {

	public function title() :string {
		return sprintf( '%s/%s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) );
	}

	public function subtitle() :string {
		return __( 'Import/Export Configuration.', 'wp-simple-firewall' );
	}
}