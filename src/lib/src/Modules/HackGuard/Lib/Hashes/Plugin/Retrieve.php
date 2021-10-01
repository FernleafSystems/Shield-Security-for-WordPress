<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Plugin;

use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib\Snapshots\StoreAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class Retrieve {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function bySlug( string $slug ) :array {
		$vo = Services::WpPlugins()->getPluginAsVo( $slug );
		if ( empty( $vo ) ) {
			throw new \Exception( 'Plugin not installed' );
		}
		return $this->byVO( $vo );
	}

	/**
	 * @throws \Exception
	 */
	public function byVO( WpPluginVo $vo ) :array {
		try {
			$hashes = $this->csHashes( $vo );
		}
		catch ( \Exception $e ) {
			$hashes = $this->localStore( $vo );
		}
		if ( empty( $hashes ) ) {
			throw new \Exception( sprintf( 'Could not locate hashes for VO: %s', $vo->slug ) );
		}
		return $hashes;
	}

	/**
	 * @throws \Exception
	 */
	public function localStore( WpPluginVo $vo ) :array {
		return ( new StoreAction\Load() )
			->setMod( $this->getMod() )
			->setAsset( $vo )
			->run()
			->getSnapData();
	}

	/**
	 * @throws \Exception
	 */
	public function csHashes( WpPluginVo $vo ) :array {
		return ( new CrowdSourcedHashes\Query\Plugin() )->getHashesFromVO( $vo );
	}
}