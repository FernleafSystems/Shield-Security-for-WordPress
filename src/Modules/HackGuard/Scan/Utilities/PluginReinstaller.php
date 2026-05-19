<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange\Cleanup;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class PluginReinstaller {

	private Cleanup $assetCleanup;

	public function __construct( ?Cleanup $assetCleanup = null ) {
		$this->assetCleanup = $assetCleanup ?? new Cleanup();
	}

	public function eligiblePlugin( string $file ) :?WpPluginVo {
		$file = \trim( $file );
		if ( $file === '' ) {
			return null;
		}

		$plugins = Services::WpPlugins();
		$plugin = $plugins->getPluginAsVo( $file );
		if ( !$plugin instanceof WpPluginVo
			 || $plugin->asset_type !== 'plugin'
			 || !$plugin->isWpOrg()
			 || $plugins->isUpdateAvailable( $plugin->file ) ) {
			return null;
		}

		return $plugin;
	}

	public function reinstall( string $file ) :bool {
		$plugin = $this->eligiblePlugin( $file );
		if ( !$plugin instanceof WpPluginVo || !Services::WpPlugins()->reinstall( $plugin->file ) ) {
			return false;
		}

		$this->deleteSnapshot( $plugin );
		$this->assetCleanup->run( 'plugin', $plugin->file );

		return true;
	}

	protected function deleteSnapshot( WpPluginVo $plugin ) :void {
		( new Delete() )
			->setAsset( $plugin )
			->run();
	}
}
