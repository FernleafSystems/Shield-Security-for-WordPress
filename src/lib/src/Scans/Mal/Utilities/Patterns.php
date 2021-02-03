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

		$cacher = new Cache\CacheDefVO();
		$cacher->dir = $mod->getTempDir();
		if ( !empty( $cacher->dir ) ) {
			$cacher->file_fragment = 'cache_patterns.txt';
			$cacher->expiration = HOUR_IN_SECONDS;
			( new Cache\LoadFromCache() )
				->setCacheDef( $cacher )
				->load();
		}

		if ( empty( $cacher->data ) ) {
			$token = $this->getCon()
						  ->getModule_License()
						  ->getWpHashesTokenManager()
						  ->getToken();
			// First attempt to download from WP Hashes API.
			$patterns = ( new Malware\Patterns\Retrieve( $token ) )->getPatterns();

			// Fallback to original method
			if ( !is_array( $patterns ) || empty( $patterns[ 'simple' ] ) || empty( $patterns[ 'regex' ] ) ) {
				/** @var Modules\HackGuard\Options $opts */
				$opts = $this->getOptions();
				$patterns = [
					'simple' => $opts->getMalSignaturesSimple(),
					'regex'  => $opts->getMalSignaturesRegex(),
				];
			}

			$cacher->data = $patterns;
			( new Cache\StoreToCache() )
				->setCacheDef( $cacher )
				->store();
		}

		return $cacher->data;
	}
}
