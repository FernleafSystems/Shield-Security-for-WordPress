<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class IsRegular
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Backup {

	/**
	 * @param string $sTo
	 * @return bool
	 * @throws \Exception
	 */
	public function run( $sTo ) {
		$oFs = Services::WpFs();
		$sContent = $oFs->getContent_WpConfig();
		if ( empty( $sContent ) ) {
			throw new \Exception( 'WP Config contents were empty' );
		}
		return sha1( base64_decode( $oFs->getFileContent( $sTo ) ) ) === ( new Hash() )->run();
	}
}