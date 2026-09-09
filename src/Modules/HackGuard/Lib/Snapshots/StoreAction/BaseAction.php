<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class BaseAction {

	use PluginControllerConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $asset;

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->asset;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return static
	 */
	public function setAsset( $asset ) {
		$this->asset = $asset;
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	protected function getNewStore( bool $createWorkingDir = true ) :Snapshots\Store {
		$workingDir = ( new Snapshots\HashesStorageDir() )->getTempDir( $createWorkingDir );
		if ( empty( $workingDir ) ) {
			throw new \Exception( __( 'Snapshot store directory is unavailable.', 'wp-simple-firewall' ) );
		}
		return ( new Snapshots\Store( $this->getAsset(), true ) )
			->setWorkingDir( $workingDir );
	}

	protected function generateMeta() :array {
		$asset = $this->getAsset();
		return [
			'ts'           => Services::Request()->ts(),
			'snap_version' => self::con()->cfg->version(),
			'cs_hashes_at' => 0,
			'unique_id'    => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			'name'         => $asset->asset_type === 'plugin' ? $asset->Name : $asset->wp_theme->get( 'Name' ),
			'version'      => $asset->version,
			'algo'         => 'md5',
		];
	}
}
