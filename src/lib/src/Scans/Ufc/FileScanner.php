<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $fullPath
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		$item = null;

		if ( !$this->isExcluded( $fullPath ) ) {
			/** @var ResultItem $item */
			$item = $this->getScanController()->getNewResultItem();
			$item->path_full = $fullPath;
			$item->path_fragment = Services::CoreFileHashes()->getFileFragment( $fullPath );
		}

		return $item;
	}

	private function isExcluded( string $fullPath ) :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$path = wp_normalize_path( $fullPath );
		$filename = basename( $path );

		$excluded = false;

		$exclusions = is_array( $opts->getOpt( 'ufc_exclusions', [] ) ) ? $opts->getOpt( 'ufc_exclusions', [] ) : [];
		foreach ( $exclusions as $exclusion ) {

			if ( preg_match( '/^#(.+)#[a-z]*$/i', $exclusion, $matches ) ) { // it's regex
				$excluded = @preg_match( stripslashes( $exclusion ), $path );
			}
			else {
				$exclusion = wp_normalize_path( $exclusion );
				if ( strpos( $exclusion, '/' ) === false ) { // filename only
					$excluded = $filename === $exclusion;
				}
				else {
					$excluded = strpos( $path, $exclusion ) !== false;
				}
			}

			if ( $excluded ) {
				break;
			}
		}
		return (bool)$excluded;
	}
}