<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildAll {

	use ModConsumer;

	/**
	 */
	public function build() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $oAsset ) {
			try {
				( new Build() )
					->setMod( $this->getMod() )
					->setAsset( $oAsset )
					->build();
			}
			catch ( \Exception $oE ) {
			}
		}
	}
}