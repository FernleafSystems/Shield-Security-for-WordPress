<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PerformAction
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class PerformAction extends BaseOps {

	/**
	 * @param int    $nLockID
	 * @param string $sAction
	 * @return string
	 * @throws \Exception
	 */
	public function run( $nLockID, $sAction ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		if ( !in_array( $sAction, [ 'accept', 'restore', 'diff' ] ) ) {
			throw new \Exception( __( 'Not a supported file lock action.', 'wp-simple-firewall' ) );
		}
		if ( !is_numeric( $nLockID ) ) {
			throw new \Exception( __( 'Please select a valid file.', 'wp-simple-firewall' ) );
		}
		$oLock = $oMod->getDbHandler_FileLocker()
					  ->getQuerySelector()
					  ->byId( (int)$nLockID );
		if ( !$oLock instanceof Databases\FileLocker\EntryVO ) {
			throw new \Exception( __( 'Not valid file lock ID.', 'wp-simple-firewall' ) );
		}

		switch ( $sAction ) {
			case 'accept':
				$mResult = ( new Accept() )
					->setMod( $this->getMod() )
					->run( $oLock );
				break;
			case 'diff':
				$mResult = $this->diff( $oLock );
				break;
			case 'restore':
				$mResult = ( new Restore() )
					->setMod( $this->getMod() )
					->run( $oLock );
				break;
			default:
				$mResult = false;
				break;
		}
		return $mResult;
	}

	/**
	 * @param Databases\FileLocker\EntryVO $oLock
	 * @return string
	 * @throws \Exception
	 */
	protected function diff( Databases\FileLocker\EntryVO $oLock ) {
		$oFS = Services::WpFs();

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