<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\MoveHashFiles;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	/**
	 * Repairs the state where the PTGuard was recreating multiple directories for the ptguard files.
	 * Here we delete everything except the first valid PTGuard dir we find.
	 *
	 * Going forward from 16.1.14, we don't attempt to migrate. We should never have been repeatedly trying to migrate
	 * in the first place - it should have been an upgrade process.
	 */
	protected function upgrade_16114() {
		$FS = Services::WpFs();
		$firstAcceptableDir = null;
		foreach ( $FS->getAllFilesInDir( $this->getCon()->cache_dir_handler->dir() ) as $fileItem ) {
			if ( $FS->isDir( $fileItem ) ) {
				$dirBase = basename( $fileItem );
				if ( $dirBase === 'ptguard' ) {
					$FS->deleteDir( $fileItem );
				}
				elseif ( preg_match( sprintf( '#^ptguard-[a-z0-9]{%s}$#i', 16 ), $dirBase ) ) {
					if ( empty( $firstAcceptableDir ) ) {
						$firstAcceptableDir = $fileItem;
					}
					else {
						$FS->deleteDir( $fileItem );
					}
				}
			}
		}
	}

	protected function upgrade_1617() {
		( new MoveHashFiles() )
			->setMod( $this->getMod() )
			->run();
	}
}