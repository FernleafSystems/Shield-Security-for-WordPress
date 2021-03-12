<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TourManager
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib
 */
class TourManager {

	use ModConsumer;

	public function getAllTours() :array {
		return [
			'dashboard_v1'
		];
	}

	/**
	 * @param string $tourKey
	 * @return bool
	 */
	public function isCompleted( string $tourKey ) :bool {
		try {
			$tours = $this->getTours();
			$shown = isset( $tours[ $tourKey ] ) && $tours[ $tourKey ] > 0;
		}
		catch ( \Exception $e ) {
			$shown = true; // in-case there's a meta saving issue.
		}
		return $shown;
	}

	/**
	 * @param string $tourKey
	 * @return $this
	 */
	public function setCompleted( string $tourKey ) {
		$tourKey = sanitize_key( $tourKey );
		if ( !empty( $tourKey ) ) {
			try {
				$tours = $this->getTours();
				$tours[ $tourKey ] = Services::Request()->ts();
				$this->getCon()->getCurrentUserMeta()->tours = $tours;
			}
			catch ( \Exception $e ) {
			}
		}
		return $this;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getTours() :array {
		$meta = $this->getCon()->getCurrentUserMeta();
		if ( empty( $meta ) ) {
			throw new \Exception( 'Not logged in or invalid user meta' );
		}
		return is_array( $meta->tours ) ? $meta->tours : [];
	}
}
