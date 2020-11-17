<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

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
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !in_array( $sAction, [ 'accept', 'restore', 'diff' ] ) ) {
			throw new \Exception( __( 'Not a supported file lock action.', 'wp-simple-firewall' ) );
		}
		if ( !is_numeric( $nLockID ) ) {
			throw new \Exception( __( 'Please select a valid file.', 'wp-simple-firewall' ) );
		}
		$oLock = $mod->getDbHandler_FileLocker()
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
				$mResult = ( new Diff() )
					->setMod( $this->getMod() )
					->run( $oLock );
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
}