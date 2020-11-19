<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\ConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class LoadConfig {

	use PluginControllerConsumer;

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var string
	 */
	private $store_key;

	public function __construct( string $path, string $storeKey ) {
		$this->path = $path;
		$this->store_key = $storeKey;
	}

	/**
	 * @return ConfigVO
	 * @throws \Exception
	 */
	public function run() :ConfigVO {
		$def = $this->fromWp();
		$rebuild = empty( $def ) || !is_array( $def );

		$specHash = sha1_file( $this->path );
		if ( !$rebuild ) {
			$con = $this->getCon();
			$version = $def[ 'properties' ][ 'version' ] ?? '0';

			$rebuild = empty( $def[ 'hash' ] ) || !hash_equals( $def[ 'hash' ], $specHash )
					   || ( $version !== Services::WpPlugins()->getPluginAsVo( $con->getPluginBaseFile() )->Version );
			$def[ 'hash' ] = $specHash;
		}

		$cfg = ( new ConfigVO() )->applyFromArray( $rebuild ? $this->fromFile() : $def );
		$cfg->hash = $specHash;
		$cfg->rebuilt = $rebuild;

		return $cfg;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function fromFile() :array {
		return Read::FromFile( $this->path );
	}

	/**
	 * @return array|null
	 */
	public function fromWp() {
		return Transient::Get( $this->store_key );
	}
}