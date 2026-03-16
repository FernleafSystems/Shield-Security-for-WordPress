<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class MaintenancePluginsService extends Plugins {

	public function __construct( private array $fixture ) {
	}

	public function getUpdates( $bForceUpdateCheck = false ) {
		return $this->fixture[ 'updates' ];
	}

	public function getPlugins() :array {
		return $this->fixture[ 'plugins' ];
	}

	public function getActivePlugins() :array {
		return $this->fixture[ 'active' ];
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->fixture[ 'plugin_vos' ][ $file ] ?? null;
	}

	public function getUrl_Activate( $file ) :string {
		return (string)( $this->fixture[ 'activate_urls' ][ $file ] ?? '/wp-admin/plugins.php?action=activate&plugin='.$file );
	}

	public function getUrl_Upgrade( $file ) :string {
		return (string)( $this->fixture[ 'upgrade_urls' ][ $file ] ?? '/wp-admin/update.php?action=upgrade-plugin&plugin='.$file );
	}
}
