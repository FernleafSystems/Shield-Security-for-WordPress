<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

class Patterns {

	use ModConsumer;

	/**
	 * @return string[][]
	 */
	public function retrieve() :array {
		$cacher = new Cache\CacheDefVO();
		$cacher->dir = $this->getCon()->cache_dir_handler->buildSubDir( 'scans' );
		if ( !empty( $cacher->dir ) ) {
			$cacher->file_fragment = 'cache_patterns.txt';
			$cacher->expiration = \HOUR_IN_SECONDS;
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
				$patterns = [
					'simple' => $this->opts()->getMalSignaturesSimple(),
					'regex'  => $this->opts()->getMalSignaturesRegex(),
				];
			}

			$cacher->data = $patterns;
			if ( !empty( $cacher->dir ) ) {
				( new Cache\StoreToCache() )
					->setCacheDef( $cacher )
					->store();
			}
		}

		return is_array( $cacher->data ) ? $cacher->data : [];
	}
}
