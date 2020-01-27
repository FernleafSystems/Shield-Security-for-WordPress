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

	/**
	 * @param string $sTourKey
	 * @return bool
	 */
	public function isCompleted( $sTourKey ) {
		try {
			$aTrs = $this->getTours();
			$bShown = isset( $aTrs[ $sTourKey ] ) && $aTrs[ $sTourKey ] > 0;
		}
		catch ( \Exception $oE ) {
			$bShown = true; // in-case there's a meta saving issue.
		}
		return $bShown;
	}

	/**
	 * @param string $sTourKey
	 * @return $this
	 */
	public function setCompleted( $sTourKey ) {
		$sTourKey = sanitize_key( $sTourKey );
		if ( !empty( $sTourKey ) ) {
			try {
				$aTrs = $this->getTours();
				$aTrs[ $sTourKey ] = Services::Request()->ts();
				$this->getCon()
					 ->getCurrentUserMeta()->tours = $aTrs;
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	protected function getTours() {
		$oMeta = $this->getCon()->getCurrentUserMeta();
		if ( empty( $oMeta ) ) {
			throw new \Exception( 'Not logged in or invalid user meta' );
		}
		return is_array( $oMeta->tours ) ? $oMeta->tours : [];
	}
}
