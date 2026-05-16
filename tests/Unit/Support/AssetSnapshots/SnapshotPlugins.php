<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class SnapshotPlugins extends Plugins {

	/**
	 * @var SnapshotPluginVo[]
	 */
	private array $plugins;

	/**
	 * @param SnapshotPluginVo[] $plugins
	 */
	public function __construct( array $plugins ) {
		$this->plugins = $plugins;
	}

	/**
	 * @return SnapshotPluginVo[]
	 */
	public function getPluginsAsVo() :array {
		return $this->plugins;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		foreach ( $this->plugins as $plugin ) {
			if ( $plugin->file === $file ) {
				return $plugin;
			}
		}
		return null;
	}
}
