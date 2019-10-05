<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

/**
 * Class Whitelist
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities
 */
class Whitelist {

	use ModConsumer;

	/**
	 * @return string[][]
	 */
	public function retrieve() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oCacheDef = new Cache\CacheDefVO();
		$oCacheDef->dir = $oMod->getTempDir();
		if ( empty( $oCacheDef->dir ) ) {
			$oCacheDef->data = [];
		}
		else {
			$oCacheDef->file_fragment = 'cache_whitelist_confidence.txt';
			$oCacheDef->expiration = MINUTE_IN_SECONDS*10;
			( new Cache\LoadFromCache() )
				->setCacheDef( $oCacheDef )
				->load();
			if ( empty( $oCacheDef->data ) ) {
				$oCacheDef->data = ( new Malware\Whitelist\Retrieve() )->getWhitelist();
				( new Cache\StoreToCache() )
					->setCacheDef( $oCacheDef )
					->store();
			}
		}

		return $oCacheDef->data;
	}
}
