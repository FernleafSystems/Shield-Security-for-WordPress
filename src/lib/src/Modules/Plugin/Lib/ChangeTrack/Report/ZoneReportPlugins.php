<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Services\Services;

class ZoneReportPlugins extends BaseZoneReportPluginsThemes {

	public function getZoneName() :string {
		return __( 'Plugins' );
	}

	protected function getItemLink( array $item ) :array {
		return [
			'href' => Services::WpGeneral()->getAdminUrl_Plugins(),
			'text' => __( 'Plugins' ),
		];
	}
}