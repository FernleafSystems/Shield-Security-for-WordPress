<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;

class PerformAction extends BaseOps {

	/**
	 * @return bool|string
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock, string $action ) {

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
				throw new \Exception( __( 'Not a supported file lock action.', 'wp-simple-firewall' ) );
		}
		return $mResult;
	}
}