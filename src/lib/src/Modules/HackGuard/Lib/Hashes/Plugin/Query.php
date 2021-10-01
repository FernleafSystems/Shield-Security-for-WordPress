<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Plugin;

use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Query {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function fileExistsInHash( string $path ) :bool {
		return !empty( $this->getHashes( $path ) );
	}

	/**
	 * @param string $path
	 * @return array|null
	 * @throws \Exception
	 */
	public function getHashes( string $path ) :array {
		$pluginFiles = new Files();
		$vo = $pluginFiles->findPluginFromFile( $path );
		if ( empty( $vo ) ) {
			throw new \Exception( 'Not a plugin file path' );
		}
		$fragment = $pluginFiles->getPluginPathFragmentFromPath( $path );
		$assetHashes = ( new Retrieve() )->byVO( $vo );
		$hash = $assetHashes[ $fragment ] ?? ( $assetHashes[ strtolower( $fragment ) ] ?? null );
		if ( empty( $hash ) ) {
			throw new \Exception( 'No hashes exist for file.' );
		}
		return is_array( $hash ) ? $hash : [ $hash ];
	}

	/**
	 * @param string $fullPath
	 * @return array|null
	 * @throws \Exception
	 */
	public function verifyHash( string $fullPath ) :bool {
		$verified = false;
		$hasher = new CompareHash();
		foreach ( $this->getHashes( $fullPath ) as $hash ) {
			if ( $hasher->isEqualFile( $fullPath, $hash ) ) {
				$verified = true;
				break;
			}
		}
		return $verified;
	}
}