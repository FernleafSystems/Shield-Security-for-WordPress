<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class TourManager {

	use ModConsumer;

	public const MOD = ModCon::SLUG;

	public function getAllTours() :array {
		return [
			'navigation_v1',
		];
	}

	public function setCompleted( string $tourKey ) {
		$tourKey = sanitize_key( $tourKey );
		$meta = $this->getCon()->getCurrentUserMeta();
		if ( !empty( $tourKey ) && !empty( $meta ) ) {
			$meta->tours = array_intersect_key(
				array_merge( $this->getAllTours(), [
					$tourKey => Services::Request()->ts()
				] ),
				array_flip( $this->getAllTours() )
			);
		}
	}

	public function getUserTourStates() :array {
		$meta = $this->getCon()->getCurrentUserMeta();
		return ( !empty( $meta ) && is_array( $meta->tours ) ) ? $meta->tours : [];
	}
}
