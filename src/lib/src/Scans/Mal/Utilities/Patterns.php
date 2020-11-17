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
		/** @var Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$oCacheDef = new Cache\CacheDefVO();
		$oCacheDef->dir = $mod->getTempDir();
		if ( !empty( $oCacheDef->dir ) ) {
			$oCacheDef->file_fragment = 'cache_patterns.txt';
			$oCacheDef->expiration = HOUR_IN_SECONDS;
			( new Cache\LoadFromCache() )
				->setCacheDef( $oCacheDef )
				->load();
		}

		if ( empty( $oCacheDef->data ) ) {
			$sApiToken = $this->getCon()
							  ->getModule_License()
							  ->getWpHashesTokenManager()
							  ->getToken();
			// First attempt to download from WP Hashes API.
			$aPatts = ( new Malware\Patterns\Retrieve( $sApiToken ) )->getPatterns();

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
