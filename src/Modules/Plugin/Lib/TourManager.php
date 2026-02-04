<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class TourManager {

	use PluginControllerConsumer;

	public function getAllTours() :array {
		return [
			'navigation_v1',
		];
	}

	public function getStates() :array {
		$allowed = self::con()->isPluginAdminPageRequest()
				   && Services::Request()->query( PluginNavs::FIELD_NAV ) !== PluginNavs::NAV_WIZARD;
		$forced = Services::Request()->query( 'force_tour' ) == '1';
		return [
			'navigation_v1' => [
				'is_available' => $forced || ( $allowed && !$this->userSeenTour( 'navigation_v1' ) ),
			],
		];
	}

	public function setCompleted( string $tourKey ) {
		$tourKey = sanitize_key( $tourKey );
		$meta = self::con()->user_metas->current();
		if ( !empty( $tourKey ) && !empty( $meta ) ) {
			$meta->tours = \array_intersect_key(
				\array_merge( $this->getAllTours(), [
					$tourKey => Services::Request()->ts()
				] ),
				\array_flip( $this->getAllTours() )
			);
		}
	}

	public function getUserTourStates() :array {
		$meta = self::con()->user_metas->current();
		return ( !empty( $meta ) && \is_array( $meta->tours ) ) ? $meta->tours : [];
	}

	public function userSeenTour( string $tour ) :bool {
		return ( $this->getUserTourStates()[ $tour ] ?? 0 ) > 0;
	}
}