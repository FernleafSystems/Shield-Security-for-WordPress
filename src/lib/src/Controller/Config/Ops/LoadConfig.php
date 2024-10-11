<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\{
	Config\ConfigVO,
	Exceptions\VersionMismatchException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadConfig {

	use PluginControllerConsumer;

	private string $path;

	private string $store_key;

	/**
	 * @throws \Exception
	 */
	public function __construct( string $path, string $storeKey ) {
		if ( !Services::WpFs()->isAccessibleFile( $path ) ) {
			throw new \Exception( sprintf( "Path to plugin config (%s) doesn't exist. Please reinstall the plugin.", $path ) );
		}
		$this->path = $path;
		$this->store_key = $storeKey;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :ConfigVO {
		$WPP = Services::WpPlugins();

		$def = $this->parseDef();
		$rebuild = empty( $def ) || !\is_array( $def ) || ( empty( $def[ 'config_spec' ] ) && empty( $def[ 'configuration' ] ) );

		$specHash = \hash_file( 'sha1', $this->path );
		$previousVersion = ( \is_array( $def ) && !empty( $def[ 'previous_version' ] ) ) ? $def[ 'previous_version' ] : null;
		if ( !$rebuild ) {
			$version = $def[ 'properties' ][ 'version' ] ?? '0';

			$rebuild = empty( $def[ 'hash' ] ) || !\hash_equals( $def[ 'hash' ], $specHash )
					   || ( $version !== $WPP->getPluginAsVo( self::con()->base_file )->Version );
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

		if ( $cfg->properties[ 'version' ] !== $WPP->getPluginAsVo( self::con()->base_file )->Version ) {
			throw new VersionMismatchException();
		}

		return $cfg;
	}

	/**
	 * With WP 6.6 we predict nervous breakdowns from the fact that SiteHealth will report that the total Autoload
	 * data is "too big" (i.e. bigger than the arbitrarily defined 800KB).
	 *
	 * So to offset this potential meltdown, we deflate/inflate the data, moving down from ~180KB to ~30KB.
	 *
	 * There is a performance cost to this. Basic timer tests show that without compression, it takes 0.001s to
	 * process, and with compression, it takes 0.002s. Not huge by any measure.
	 */
	private function parseDef() :?array {
		$def = Services::WpGeneral()->getOption( $this->store_key );
		if ( \is_string( $def ) ) {
			$gz = @\base64_decode( $def );
			if ( !empty( $gz ) ) {
				$serial = @\gzinflate( $gz );
				if ( !empty( $serial ) ) {
					$maybeDef = @\unserialize( $serial );
					if ( !empty( $maybeDef ) ) {
						$def = $maybeDef;
					}
				}
			}
		}
		return \is_array( $def ) ? $def : null;
	}

	/**
	 * @throws \Exception
	 */
	public function fromFile() :array {
		return Read::FromFile( $this->path );
	}
}