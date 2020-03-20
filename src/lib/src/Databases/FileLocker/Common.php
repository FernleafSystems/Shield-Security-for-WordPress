<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

/**
 * Trait Filters
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker
 */
trait Common {

	/**
	 * @param string $sFile
	 * @return $this
	 */
	public function filterByFile( $sFile ) {
		return $this->addWhereEquals( 'file', base64_encode( $sFile ) );
	}

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function filterByHashOriginal( $sHash ) {
		return $this->addWhereEquals( 'hash_original', base64_encode( $sHash ) );
	}

	/**
	 * @return $this
	 */
	public function filterByProblem() {
		return $this->addWhereNewerThan( 0, 'detected_at' );
	}
}