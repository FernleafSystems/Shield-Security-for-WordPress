<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class RenderHtmlFileDiff
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class RenderHtmlFileDiff extends BaseOps {

	/**
	 * @param int $nLockID
	 * @return string
	 * @throws \Exception
	 */
	public function run( $nLockID ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();
		$oFS = Services::WpFs();

		$sDiff = null;

		if ( !is_numeric( $nLockID ) ) {
			throw new \Exception( __( 'Please select a valid file.', 'wp-simple-firewall' ) );
		}
		$oLock = $oDbH->getQuerySelector()->byId( (int)$nLockID );
		if ( !$oLock instanceof Databases\FileLocker\EntryVO ) {
			throw new \Exception( __( 'Not valid file lock ID.', 'wp-simple-firewall' ) );
		}
		if ( !$oFS->isFile( $oLock->file ) ) {
			throw new \Exception( __( 'File is missing or could not be read.', 'wp-simple-firewall' ) );
		}

		$sContent = Services::WpFs()->getFileContent( $oLock->file );
		if ( empty( $sContent ) ) {
			throw new \Exception( __( 'File is empty or could not be read.', 'wp-simple-firewall' ) );
		}
		return wp_text_diff(
			( new ReadOriginalFileContent() )
				->setMod( $this->getMod() )
				->run( $oLock ),
			$sContent
		);
	}
}