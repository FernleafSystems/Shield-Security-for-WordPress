<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Store;
use FernleafSystems\Wordpress\Services\Services;

class MoveHashFiles extends BaseAction {

	public function run() {
		$FS = Services::WpFs();
		if ( $this->isTempDirAvailable() ) {
			foreach ( ( new FindAssetsToSnap() )->run() as $asset ) {
				$oldStore = ( new Store( $asset, false ) )->setWorkingDir( $this->getTempDir() );
				$newStore = ( new Store( $asset, true ) )->setWorkingDir( $this->getTempDir() );
				if ( $FS->isAccessibleFile( $oldStore->getSnapStorePath() ) ) {
					$FS->move( $oldStore->getSnapStorePath(), $newStore->getSnapStorePath() );
				}
				if ( $FS->isAccessibleFile( $oldStore->getSnapStoreMetaPath() ) ) {
					$FS->move( $oldStore->getSnapStoreMetaPath(), $newStore->getSnapStoreMetaPath() );
				}
			}
		}
	}
}