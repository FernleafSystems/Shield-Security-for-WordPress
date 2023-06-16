<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Services\Services;

class ZoneReportThemes extends BaseZoneReportPluginsThemes {

	public function getZoneName() :string {
		return __( 'Themes' );
	}

	protected function getItemLink( array $item ) :array {
		return [
			'href' => Services::WpGeneral()->getAdminUrl_Themes(),
			'text' => __( 'Themes' ),
		];
	}
}