<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class Repair extends Scans\Base\BaseRepair {

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function repairItem( $oItem ) {
		$sPath = trim( wp_normalize_path( $oItem->path_fragment ), '/' );
		return ( new Scans\Helpers\WpCoreFile() )->replace( $sPath );
	}
}