<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Plugin,
	Theme
};

abstract class BasePluginThemeFile extends BaseScan {

	protected $asset = null;

	protected function canScan() :bool {
		$can = parent::canScan();
		if ( $can ) {
			$this->asset = ( new Plugin\Files() )->findPluginFromFile( $this->pathFull );
			if ( empty( $this->asset ) ) {
				$this->asset = ( new Theme\Files() )->findThemeFromFile( $this->pathFull );
			}
			$can = !empty( $this->asset );
		}
		return $can;
	}
}