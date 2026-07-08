<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities\IsVulnerable;

class Scan extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$results = [];
		foreach ( $action->items as $file ) {
			$action->tickProgress();

			$isVulnerable = false;

			if ( \str_contains( $file, '/' ) ) { // plugin file
				$WPP = Services::WpPlugins();
				$slug = $WPP->getSlug( $file );
				if ( empty( $slug ) ) {
					$slug = \dirname( $file );
				}

				// Turns out that some plugins don't provide ->Version
				$plugin = $WPP->getPluginAsVo( $file );
				if ( \strlen( (string)$plugin->Version ) > 0 ) {
					$isVulnerable = ( new IsVulnerable() )->plugin( $slug, $plugin->Version );
				}
			}
			else { // theme dir
				$theme = Services::WpThemes()->getTheme( $file );
				$version = empty( $theme ) ? '' : $theme->get( 'Version' );
				$isVulnerable = !empty( $version ) && ( new IsVulnerable() )->theme( $file, $version );
			}

			if ( $isVulnerable ) {
				$results[] = [
					'slug'          => $file,
					'is_vulnerable' => true,
				];
			}
		}
		$action->results = $results;
	}
}
