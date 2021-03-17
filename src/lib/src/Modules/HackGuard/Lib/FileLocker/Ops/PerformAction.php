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
	 * @param int    $lockID
	 * @param string $action
	 * @return string
	 * @throws \Exception
	 */
	public function run( $lockID, $action ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !in_array( $action, [ 'accept', 'restore', 'diff' ] ) ) {
			throw new \Exception( __( 'Not a supported file lock action.', 'wp-simple-firewall' ) );
		}
		if ( !is_numeric( $lockID ) ) {
			throw new \Exception( __( 'Please select a valid file.', 'wp-simple-firewall' ) );
		}
		$lock = $mod->getDbHandler_FileLocker()
					->getQuerySelector()
					->byId( (int)$lockID );
		if ( !$lock instanceof Databases\FileLocker\EntryVO ) {
			throw new \Exception( __( 'Not valid file lock ID.', 'wp-simple-firewall' ) );
		}

		switch ( $action ) {
			case 'accept':
				$mResult = ( new Accept() )
					->setMod( $this->getMod() )
					->run( $lock );
				break;
			case 'diff':
				$mResult = ( new Diff() )
					->setMod( $this->getMod() )
					->run( $lock );
				break;
			case 'restore':
				$mResult = ( new Restore() )
					->setMod( $this->getMod() )
					->run( $lock );
				break;
			default:
				$mResult = false;
				break;
		}
		return $mResult;
	}
}