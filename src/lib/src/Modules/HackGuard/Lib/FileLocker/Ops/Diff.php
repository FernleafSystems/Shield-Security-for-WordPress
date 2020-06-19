<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

/**
 * Class Diff
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Diff extends BaseOps {

	/**
	 * @param FileLocker\EntryVO $oLock
	 * @return bool
	 * @throws \Exception
	 */
	public function run( $oLock ) {

		$oFS = Services::WpFs();

		if ( !$oFS->isFile( $oLock->file ) ) {
			throw new \Exception( __( 'File is missing or could not be read.', 'wp-simple-firewall' ) );
		}

		$sContent = Services::WpFs()->getFileContent( $oLock->file );
		if ( empty( $sContent ) ) {
			throw new \Exception( __( 'File is empty or could not be read.', 'wp-simple-firewall' ) );
		}

		$sOriginal = ( new ReadOriginalFileContent() )
			->setMod( $this->getMod() )
			->run( $oLock );

		try {
			$sDiff = $this->useWpHashes( $sOriginal, $sContent );
		}
		catch ( \Exception $oE ) {
			$sDiff = $this->useWpDiff( $sOriginal, $sContent );
		}
		return $sDiff;
	}

	/**
	 * @param string $sOriginal
	 * @param string $sCurrent
	 * @return string
	 * @throws \Exception
	 */
	private function useWpHashes( $sOriginal, $sCurrent ) {
		$aResult = ( new WpHashes\Util\Diff() )->getDiff( $sOriginal, $sCurrent );
		if ( !is_array( $aResult ) || empty( $aResult[ 'html' ] ) ) {
			throw new \Exception( 'Could not get a valid diff from WP Hashes.' );
		}
		return base64_decode( $aResult[ 'html' ] );
	}

	/**
	 * @param string $sOriginal
	 * @param string $sCurrent
	 * @return string
	 */
	private function useWpDiff( $sOriginal, $sCurrent ) {
		return wp_text_diff(
			$sOriginal,
			$sCurrent
		);
	}
}