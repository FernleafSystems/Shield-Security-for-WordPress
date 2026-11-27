<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;

class InvestigationTrafficTableData extends BuildTrafficTableData {

	use InvestigationContextLinks;

	protected function getTrafficUserDisplay( int $uid ) :string {
		return $uid <= 0
			? esc_html__( 'No', 'wp-simple-firewall' )
			: $this->getUserHref( $uid );
	}
}
