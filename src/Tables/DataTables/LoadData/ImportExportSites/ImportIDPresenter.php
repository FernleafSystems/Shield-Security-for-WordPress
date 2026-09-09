<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites;

class ImportIDPresenter {

	public function displayValue( string $importID ) :string {
		$importID = \trim( $importID );
		return empty( $importID ) ? __( 'Not recorded yet', 'wp-simple-firewall' ) : $importID;
	}
}
