<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildStores {

	use ModConsumer;

	/**
	 */
	public function build() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $oAsset ) {
			try {
				( new BuildStore( $oAsset ) )
					->setMod( $this->getMod() )
					->build();
			}
			catch ( \Exception $oE ) {
			}
		}
	}
}