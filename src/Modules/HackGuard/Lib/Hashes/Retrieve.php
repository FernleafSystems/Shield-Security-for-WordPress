<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\AssetHashesNotFound;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Query;

class Retrieve {

	use PluginControllerConsumer;

	private const MODE_PUBLISHED_OR_STORED = 'published_or_stored';
	private const MODE_STORED = 'stored';

	/**
	 * @var array<string,array{hashes:array<string,list<string>>,trusted_source:bool,comparison_basis:string}|null>
	 */
	private static array $sources;

	public static function resetMemoization() :void {
		self::$sources = [];
	}

	public function __construct() {
		self::$sources ??= [];
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws \Exception
	 */
	public function bySlug( string $slug ) :array {
		$vo = Services::WpPlugins()->getPluginAsVo( $slug, true );
		if ( empty( $vo ) ) {
			$vo = Services::WpThemes()->getThemeAsVo( $slug, true );
			if ( empty( $vo ) ) {
				throw new \Exception( sprintf( 'Plugin or theme not installed for slug: %s', $slug ) );
			}
		}
		return $this->byVO( $vo );
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return array<string,list<string>>
	 * @throws AssetHashesNotFound|\Exception
	 */
	public function byVO( $vo ) :array {
		return $this->byVOWithSource( $vo )[ 'hashes' ];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return array{hashes:array<string,list<string>>,trusted_source:bool,comparison_basis:string}
	 * @throws AssetHashesNotFound|\Exception
	 */
	public function byVOWithSource( $vo ) :array {
		$cacheKey = $this->buildCacheKey( self::MODE_PUBLISHED_OR_STORED, $vo );
		if ( !\array_key_exists( $cacheKey, self::$sources ) ) {
			try {
				self::$sources[ $cacheKey ] = [
					'hashes'           => $this->fromCsHashes( $vo ),
					'trusted_source'   => true,
					'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE,
				];
			}
			catch ( \Exception $e ) {
				self::$sources[ $cacheKey ] = $this->byVOFromStoredSnapshot( $vo );
			}
		}

		$source = self::$sources[ $cacheKey ];
		if ( \is_null( $source ) ) {
			throw new AssetHashesNotFound( sprintf( __( 'Could not locate hashes for VO: %s', 'wp-simple-firewall' ), $vo->slug ) );
		}
		return $source;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return array{hashes:array<string,list<string>>,trusted_source:bool,comparison_basis:string}|null
	 */
	public function byVOFromStoredSnapshot( $vo ) :?array {
		$cacheKey = $this->buildCacheKey( self::MODE_STORED, $vo );
		if ( !\array_key_exists( $cacheKey, self::$sources ) ) {
			try {
				$store = ( new StoreAction\Load() )
					->setAsset( $vo )
					->run();
				$snapshot = $store->getUsableSnapshot();
				if ( \is_null( $snapshot ) ) {
					self::$sources[ $cacheKey ] = null;
				}
				else {
					$trustedSource = ( $snapshot[ 'meta' ][ 'live_hashes' ] ?? false ) === true;
					self::$sources[ $cacheKey ] = [
						'hashes'           => ( new NormalizeHashMap() )->run( $snapshot[ 'data' ] ),
						'trusted_source'   => $trustedSource,
						'comparison_basis' => $trustedSource ?
							HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE
							: HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE,
					];
				}
			}
			catch ( \Exception $e ) {
				self::$sources[ $cacheKey ] = null;
			}
		}
		return self::$sources[ $cacheKey ];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @throws \Exception
	 */
	private function fromCsHashes( $vo ) :array {
		if ( !self::con()->caps->canScanPluginsThemesRemote() && !$vo->isWpOrg() ) {
			throw new \Exception( __( 'Insufficient permissions to use crowd-sourced hashes for premium plugins/themes.', 'wp-simple-firewall' ) );
		}
		$hashes = ( new NormalizeHashMap() )->run(
			( $vo->asset_type == 'plugin' ? new Query\Plugin() : new Query\Theme() )->getHashesFromVO( $vo )
		);
		if ( empty( $hashes ) ) {
			throw new \Exception( __( 'No crowd-sourced hashes available.', 'wp-simple-firewall' ) );
		}
		return $hashes;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 */
	private function buildCacheKey( string $mode, $vo ) :string {
		return \implode( '|', [
			$mode,
			(string)$vo->asset_type,
			(string)$vo->unique_id,
			(string)$vo->Version,
		] );
	}
}
