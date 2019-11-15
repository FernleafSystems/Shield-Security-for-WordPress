<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

/**
 * Class Patterns
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities
 */
class Patterns {

	use Modules\ModConsumer;

	/**
	 * @return string[][]
	 */
	public function retrieve() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oCacheDef = new Cache\CacheDefVO();
		$oCacheDef->dir = $oMod->getTempDir();
		if ( !empty( $oCacheDef->dir ) ) {
			$oCacheDef->file_fragment = 'cache_patterns.txt';
			$oCacheDef->expiration = HOUR_IN_SECONDS;
			( new Cache\LoadFromCache() )
				->setCacheDef( $oCacheDef )
				->load();
		}

		if ( empty( $oCacheDef->data ) ) {

			// First attempt to download from WP Hashes API.
			$aPatts = ( new Malware\Patterns\Retrieve() )->getPatterns();

			// Fallback to original method
			if ( !is_array( $aPatts ) || empty( $aPatts[ 'simple' ] ) || empty( $aPatts[ 'regex' ] ) ) {
				/** @var Modules\HackGuard\Options $oOpts */
				$oOpts = $this->getOptions();
				$aPatts = [
					'simple' => $oOpts->getMalSignaturesSimple(),
					'regex'  => $oOpts->getMalSignaturesRegex(),
				];
			}

			$oCacheDef->data = $aPatts;
			( new Cache\StoreToCache() )
				->setCacheDef( $oCacheDef )
				->store();
		}

		return $oCacheDef->data;
	}
}
