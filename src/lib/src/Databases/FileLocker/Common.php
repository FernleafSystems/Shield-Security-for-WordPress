<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

trait Common {

	/**
	 * @param string $file
	 * @return $this
	 */
	public function filterByFile( $file ) {
		return $this->addWhereEquals( 'file', base64_encode( $file ) );
	}

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function filterByHashOriginal( $sHash ) {
		return $this->addWhereEquals( 'hash_original', base64_encode( $sHash ) );
	}
}