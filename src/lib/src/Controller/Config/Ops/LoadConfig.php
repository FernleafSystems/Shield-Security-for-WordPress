<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\{
	Config\ConfigVO,
	Exceptions\VersionMismatchException
};
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
	 * @throws \Exception
	 */
	public function run() :ConfigVO {
		$con = $this->getCon();
		$WPP = Services::WpPlugins();

		$def = $this->fromWp();
		$rebuild = empty( $def ) || !is_array( $def );

		$specHash = sha1_file( $this->path );
		$previousVersion = ( is_array( $def ) && !empty( $def[ 'previous_version' ] ) ) ? $def[ 'previous_version' ] : null;
		if ( !$rebuild ) {
			$version = $def[ 'properties' ][ 'version' ] ?? '0';

			$rebuild = empty( $def[ 'hash' ] ) || !hash_equals( $def[ 'hash' ], $specHash )
					   || ( $version !== $WPP->getPluginAsVo( $con->base_file )->Version );
			$def[ 'hash' ] = $specHash;
		}

		if ( $rebuild ) {
			$def = $this->fromFile();
			$def[ 'previous_version' ] = $previousVersion;
		}

		$cfg = ( new ConfigVO() )->applyFromArray( $def );
		$cfg->hash = $specHash;
		$cfg->rebuilt = $rebuild;

		if ( empty( $cfg->previous_version ) ) {
			$cfg->previous_version = $cfg->properties[ 'version' ];
		}

		if ( $cfg->properties[ 'version' ] !== $WPP->getPluginAsVo( $con->base_file )->Version ) {
			throw new VersionMismatchException();
		}

		return $cfg;
	}

	/**
	 * @throws \Exception
	 */
	public function fromFile() :array {
		return Read::FromFile( $this->path );
	}

	/**
	 * @deprecated 16.0 - remove the empty()?
	 * @return array|null
	 */
	public function fromWp() {
		$def = Services::WpGeneral()->getOption( $this->store_key );
		return empty( $def ) ? Transient::Get( $this->store_key ) : $def;
	}
}