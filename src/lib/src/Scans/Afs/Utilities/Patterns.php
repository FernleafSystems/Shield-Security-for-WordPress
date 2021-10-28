<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

use const HOUR_IN_SECONDS;

class Patterns {

	use Modules\ModConsumer;

	/**
	 * @return string[][]
	 */
	public function retrieve() {
		/** @var Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$cacher = new Cache\CacheDefVO();
		$cacher->dir = $mod->getScansTempDir();
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
			if ( !empty( $cacher->dir ) ) {
				( new Cache\StoreToCache() )
					->setCacheDef( $cacher )
					->store();
			}
		}

		return $cacher->data;
	}
}
