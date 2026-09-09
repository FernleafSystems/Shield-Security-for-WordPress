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

		$installed = $this->getInstalledPluginData( $plugin );
		if ( \is_null( $installed ) ) {
			return [];
		}

		$lastUpdatedAt = $this->getVerifiedWpOrgLastUpdatedAt( $installed[ 'slug' ], $installed[ 'version' ] );
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
		if ( $this->hasExternalUpdateUri( $pluginData ) ) {
			return false;
		}

		$id = $plugin->id;
		return \is_string( $id ) && $plugin->isWpOrg();
	}

	private function hasExternalUpdateUri( array $pluginData ) :bool {
		foreach ( [ 'UpdateURI', 'Update URI' ] as $key ) {
			if ( !\array_key_exists( $key, $pluginData ) ) {
				continue;
			}
			if ( !\is_string( $pluginData[ $key ] ) ) {
				return true;
			}
			if ( !\is_null( $this->nonEmptyString( $pluginData[ $key ] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return null|array{slug:string,version:string}
	 */
	private function getInstalledPluginData( WpPluginVo $plugin ) :?array {
		$raw = $plugin->getRawData();
		if ( \array_key_exists( 'slug', $raw ) && \is_null( $this->nonEmptyString( $raw[ 'slug' ] ) ) ) {
			return null;
		}
		$slug = $this->nonEmptyString( $plugin->slug );

		if ( \array_key_exists( 'Version', $raw ) ) {
			$version = $this->nonEmptyString( $raw[ 'Version' ] );
		}
		else {
			$public = \get_object_vars( $plugin );
			$version = \array_key_exists( 'Version', $public )
				? $this->nonEmptyString( $public[ 'Version' ] )
				: null;
		}

		return \is_null( $slug ) || \is_null( $version ) ? null : [
			'slug'    => $slug,
			'version' => $version,
		];
	}

	private function getVerifiedWpOrgLastUpdatedAt( string $slug, string $installedVersion ) :?int {
		$pluginInfo = $this->queryWpOrgPluginInfo( $slug );
		if ( \is_null( $pluginInfo ) ) {
			return null;
		}

		$api = $this->getApiPluginData( $pluginInfo );
		if ( \is_null( $api )
			 || \strcasecmp( $slug, $api[ 'slug' ] ) !== 0
			 || \version_compare( $installedVersion, $api[ 'version' ], '>' ) ) {
			return null;
		}

		$lastUpdate = \strtotime( $api[ 'last_updated' ] );
		return $lastUpdate !== false && $lastUpdate > 0 ? $lastUpdate : null;
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

	/**
	 * @return null|array{slug:string,version:string,last_updated:string}
	 */
	private function getApiPluginData( object $pluginInfo ) :?array {
		$public = \get_object_vars( $pluginInfo );
		$data = [];
		foreach ( [ 'slug', 'version', 'last_updated' ] as $key ) {
			if ( !\array_key_exists( $key, $public ) ) {
				return null;
			}
			$data[ $key ] = $this->nonEmptyString( $public[ $key ] );
			if ( \is_null( $data[ $key ] ) ) {
				return null;
			}
		}

		return $data;
	}

	private function nonEmptyString( $value ) :?string {
		if ( !\is_string( $value ) ) {
			return null;
		}

		$value = \trim( $value );
		return $value === '' ? null : $value;
	}
}
