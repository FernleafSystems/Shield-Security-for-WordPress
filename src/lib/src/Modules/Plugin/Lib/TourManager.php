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
			'dashboard_v1',
			'navigation_v1',
		];
	}

	public function isCompleted( string $tourKey ) :bool {
		try {
			$tours = $this->loadUserTourStates();
			$shown = isset( $tours[ $tourKey ] ) && $tours[ $tourKey ] > 0;
		}
		catch ( \Exception $e ) {
			$shown = true; // in-case there's a meta saving issue.
		}
		return $shown;
	}

	public function setCompleted( string $tourKey ) {
		$tourKey = sanitize_key( $tourKey );
		if ( !empty( $tourKey ) ) {
			try {
				$tours = $this->loadUserTourStates();
				$tours[ $tourKey ] = Services::Request()->ts();
				$this->getCon()->getCurrentUserMeta()->tours = $tours;
			}
			catch ( \Exception $e ) {
			}
		}
	}

	public function getUserTourStates() :array {
		try {
			$tours = $this->loadUserTourStates();
		}
		catch ( \Exception $e ) {
			$tours = [];
		}
		return $tours;
	}

	/**
	 * @throws \Exception
	 */
	private function loadUserTourStates() :array {
		$meta = $this->getCon()->getCurrentUserMeta();
		if ( empty( $meta ) ) {
			throw new \Exception( 'Not logged in or invalid user meta' );
		}
		return is_array( $meta->tours ) ? $meta->tours : [];
	}
}
