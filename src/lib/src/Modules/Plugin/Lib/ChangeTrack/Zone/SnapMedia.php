<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportMedia;

class SnapMedia extends SnapPosts {

	public const SLUG = 'media';

	public function getZoneReporterClass() :string {
		return ZoneReportMedia::class;
	}

	protected function getBaseParameters() :array {
		$params = parent::getBaseParameters();
		$params[ 'post_type' ] = 'attachment';
		unset( $params[ 'post_status' ] );
		return $params;
	}
}