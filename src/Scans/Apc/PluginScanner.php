<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class PluginScanner {

	use ScanActionConsumer;

	public function scan( string $pluginFile ) :array {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$wpPlugins = Services::WpPlugins();
		$plugin = $wpPlugins->getPluginAsVo( $pluginFile );
		$pluginData = $wpPlugins->getPlugin( $pluginFile );
		if ( !$plugin instanceof WpPluginVo
			 || !$this->isEligibleForWpOrgScan( $plugin, \is_array( $pluginData ) ? $pluginData : [] ) ) {
			return [];
		}

		$lastUpdatedAt = $this->getVerifiedWpOrgLastUpdatedAt( $plugin );
		if ( \is_null( $lastUpdatedAt )
			 || Services::Request()->ts() - $lastUpdatedAt <= $action->abandoned_limit ) {
			return [];
		}

		return [
			'slug'            => $pluginFile,
			'is_abandoned'    => true,
			'last_updated_at' => $lastUpdatedAt,
		];
	}

	private function isEligibleForWpOrgScan( WpPluginVo $plugin, array $pluginData ) :bool {
		return !$this->hasExternalUpdateUri( $pluginData ) && $plugin->isWpOrg();
	}

	private function hasExternalUpdateUri( array $pluginData ) :bool {
		return \trim( (string)( $pluginData[ 'UpdateURI' ] ?? $pluginData[ 'Update URI' ] ?? '' ) ) !== '';
	}

	private function getVerifiedWpOrgLastUpdatedAt( WpPluginVo $plugin ) :?int {
		$slug = \trim( (string)$plugin->slug );
		if ( $slug === '' ) {
			return null;
		}

		$pluginInfo = $this->queryWpOrgPluginInfo( $slug );
		if ( \is_null( $pluginInfo ) || !$this->isMatchingWpOrgPlugin( $plugin, $slug, $pluginInfo ) ) {
			return null;
		}

		return $this->parseLastUpdatedAt( $pluginInfo );
	}

	private function queryWpOrgPluginInfo( string $slug ) :?object {
		if ( !\function_exists( 'plugins_api' ) ) {
			require_once path_join( ABSPATH, 'wp-admin/includes/plugin-install.php' );
		}
		$pluginInfo = plugins_api( 'plugin_information', [
			'slug'   => $slug,
			'fields' => [
				'sections' => false,
			],
		] );

		return \is_wp_error( $pluginInfo ) || !\is_object( $pluginInfo ) ? null : $pluginInfo;
	}

	private function isMatchingWpOrgPlugin( WpPluginVo $plugin, string $slug, object $pluginInfo ) :bool {
		$apiSlug = \trim( (string)( $pluginInfo->slug ?? '' ) );
		$installedVersion = \trim( (string)$plugin->Version );
		$apiVersion = \trim( (string)( $pluginInfo->version ?? '' ) );

		return $apiSlug !== ''
			   && \strcasecmp( $slug, $apiSlug ) === 0
			   && $installedVersion !== ''
			   && $apiVersion !== ''
			   && !\version_compare( $installedVersion, $apiVersion, '>' );
	}

	private function parseLastUpdatedAt( object $pluginInfo ) :?int {
		$lastUpdated = \trim( (string)( $pluginInfo->last_updated ?? '' ) );
		if ( $lastUpdated === '' ) {
			return null;
		}

		$lastUpdate = \strtotime( $lastUpdated );
		return $lastUpdate !== false && $lastUpdate > 0 ? $lastUpdate : null;
	}
}
