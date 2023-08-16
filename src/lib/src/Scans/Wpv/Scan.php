<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities\IsVulnerable;

class Scan extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$action->results = \array_filter( \array_map(
			function ( $file ) {

				if ( \strpos( $file, '/' ) ) { // plugin file
					$WPP = Services::WpPlugins();
					$slug = $WPP->getSlug( $file );
					if ( empty( $slug ) ) {
						$slug = \dirname( $file );
					}
					$isVulnerable = ( new IsVulnerable() )->plugin( $slug, $WPP->getPluginAsVo( $file )->Version );
				}
				else { // theme dir
					$isVulnerable = ( new IsVulnerable() )
						->theme( $file, Services::WpThemes()->getTheme( $file )->get( 'Version' ) );
				}

				return $isVulnerable ? [
					'slug'          => $file,
					'is_vulnerable' => true
				] : null;
			},
			$action->items
		) );
	}
}