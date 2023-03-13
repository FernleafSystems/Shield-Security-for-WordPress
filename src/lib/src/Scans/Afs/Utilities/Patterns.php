<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malai\MalwarePatterns;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

class Patterns {

	use ModConsumer;

	/**
	 * @return array{raw: string[], iraw: string[], re: string[], functions: string[], keywords: string[]}
	 */
	public function retrieve() :array {
		$cacher = new Cache\CacheDefVO();
		$cacher->dir = $this->getCon()->cache_dir_handler->buildSubDir( 'scans' );
		if ( !empty( $cacher->dir ) ) {
			$cacher->file_fragment = 'malcache_patterns_v2.txt';
			$cacher->expiration = \DAY_IN_SECONDS;
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
			$patterns = ( new MalwarePatterns( $token ) )->retrieve();

			// Fallback to original method
			if ( empty( $patterns[ 'raw' ] ) || empty( $patterns[ 're' ] ) ) {
				$patterns = [
					'raw'       => $this->opts()->getMalSignaturesSimple(),
					're'        => $this->opts()->getMalSignaturesRegex(),
					'iraw'      => [],
					'functions' => [],
					'keywords'  => [],
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