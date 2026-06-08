<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\{
	AssetHashesNotFound,
	NonAssetFileException,
	UnrecognisedAssetFile
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Plugin,
	Theme
};

class AssetTrustResolver {

	private static array $pluginsByDir = [];

	private static array $themesByDir = [];

	private static array $contextsByPath = [];

	private static array $nonAssetMissesByPath = [];

	private static array $relativePathsByPath = [];

	public static function resetMemoization() :void {
		self::$pluginsByDir = [];
		self::$themesByDir = [];
		self::$contextsByPath = [];
		self::$nonAssetMissesByPath = [];
		self::$relativePathsByPath = [];
	}

	/**
	 * @return array{hashes:array<int, string>, trusted_source:bool, asset_type:string, asset_key:string, asset_version:string, relative_path:string}
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashDataForFile( string $path ) :array {
		return $this->getHashDataForContext( $path, $this->resolveContext( $path ) );
	}

	/**
	 * @return array{hashes:array<int, string>, trusted_source:bool, asset_type:string, asset_key:string, asset_version:string, relative_path:string}
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashDataForContext( string $path, AssetFileContext $context ) :array {
		$vo = $this->assetFromContext( $context );
		$hashSource = ( new Retrieve() )->byVOWithSource( $vo );
		$hash = $hashSource[ 'hashes' ][ $context->relativePath ]
				?? ( $hashSource[ 'hashes' ][ \strtolower( $context->relativePath ) ] ?? null );
		if ( empty( $hash ) ) {
			throw new UnrecognisedAssetFile( sprintf( 'No hashes exist for file: %s', $path ) );
		}

		return [
			'hashes'         => \is_array( $hash ) ? $hash : [ $hash ],
			'trusted_source' => $hashSource[ 'trusted_source' ],
			'asset_type'     => $context->assetType,
			'asset_key'      => $context->assetKey,
			'asset_version'  => $context->assetVersion,
			'relative_path'  => $context->relativePath,
		];
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \InvalidArgumentException
	 */
	public function verifyPath( string $path ) :HashVerificationResult {
		return $this->verifyContext( $path, $this->resolveContext( $path ) );
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \InvalidArgumentException
	 */
	public function verifyContext( string $path, AssetFileContext $context ) :HashVerificationResult {
		$verified = false;
		$hashData = $this->getHashDataForContext( $path, $context );
		$compare = new CompareHash();
		foreach ( $hashData[ 'hashes' ] as $hash ) {
			if ( $compare->isEqualFile( $path, $hash ) ) {
				$verified = true;
				break;
			}
		}

		return new HashVerificationResult(
			$verified,
			$verified && $hashData[ 'trusted_source' ],
			$hashData[ 'asset_type' ],
			$hashData[ 'asset_key' ],
			$hashData[ 'asset_version' ],
			$hashData[ 'relative_path' ]
		);
	}

	/**
	 * @throws NonAssetFileException
	 */
	public function resolveContext( string $path ) :AssetFileContext {
		$cacheKey = wp_normalize_path( $path );
		if ( isset( self::$contextsByPath[ $cacheKey ] ) ) {
			return self::$contextsByPath[ $cacheKey ];
		}
		if ( isset( self::$nonAssetMissesByPath[ $cacheKey ] ) ) {
			throw new NonAssetFileException( 'Not a plugin or theme file path.' );
		}

		try {
			$context = $this->resolvePluginContext( $path );
		}
		catch ( NonAssetFileException $e ) {
			try {
				$context = $this->resolveThemeContext( $path );
			}
			catch ( NonAssetFileException $e ) {
				self::$nonAssetMissesByPath[ $cacheKey ] = true;
				throw $e;
			}
		}

		self::$contextsByPath[ $cacheKey ] = $context;
		return $context;
	}

	/**
	 * @throws NonAssetFileException
	 */
	private function resolvePluginContext( string $path ) :AssetFileContext {
		if ( !$this->isPathInRoot( $path, WP_PLUGIN_DIR ) ) {
			throw new NonAssetFileException( 'Not a plugin file path.' );
		}

		$pluginFiles = new Plugin\Files();
		$fragment = $pluginFiles->getPluginPathFragmentFromPath( $path );
		if ( !\is_string( $fragment ) || \strpos( $fragment, '/' ) === false ) {
			throw new NonAssetFileException( 'Not a plugin file path.' );
		}

		$dir = \substr( $fragment, 0, \strpos( $fragment, '/' ) );
		$asset = $this->pluginFromDir( $dir );
		if ( !$asset instanceof WpPluginVo ) {
			throw new NonAssetFileException( 'Not an installed plugin file path.' );
		}

		return new AssetFileContext(
			'plugin',
			(string)$asset->unique_id,
			(string)$asset->Version,
			$this->relativePath( 'plugin', $path, $fragment )
		);
	}

	/**
	 * @throws NonAssetFileException
	 */
	private function resolveThemeContext( string $path ) :AssetFileContext {
		if ( !$this->isPathInRoot( $path, get_theme_root() ) ) {
			throw new NonAssetFileException( 'Not a theme file path.' );
		}

		$themeFiles = new Theme\Files();
		$fragment = $themeFiles->getThemePathFragmentFromPath( $path );
		if ( !\is_string( $fragment ) || \strpos( $fragment, '/' ) === false ) {
			throw new NonAssetFileException( 'Not a theme file path.' );
		}

		$dir = \substr( $fragment, 0, \strpos( $fragment, '/' ) );
		$asset = $this->themeFromDir( $dir );
		if ( !$asset instanceof WpThemeVo ) {
			throw new NonAssetFileException( 'Not an installed theme file path.' );
		}

		return new AssetFileContext(
			'theme',
			(string)$asset->unique_id,
			(string)$asset->Version,
			$this->relativePath( 'theme', $path, $fragment )
		);
	}

	private function relativePath( string $type, string $path, string $fragment ) :string {
		$cacheKey = $type.'|'.wp_normalize_path( $path );
		if ( !isset( self::$relativePathsByPath[ $cacheKey ] ) ) {
			self::$relativePathsByPath[ $cacheKey ] = \substr( $fragment, \strpos( $fragment, '/' ) + 1 );
		}
		return self::$relativePathsByPath[ $cacheKey ];
	}

	private function isPathInRoot( string $path, string $root ) :bool {
		$path = wp_normalize_path( $path );
		if ( \preg_match( '#^(?:[A-Z]:)?/#i', $path ) !== 1 ) {
			$path = wp_normalize_path( path_join( ABSPATH, $path ) );
		}

		$root = \rtrim( wp_normalize_path( $root ), '/' ).'/';
		return \str_starts_with( $path, $root );
	}

	private function pluginFromDir( string $dir ) :?WpPluginVo {
		if ( !\array_key_exists( $dir, self::$pluginsByDir ) ) {
			$asset = null;
			$plugins = Services::WpPlugins();
			foreach ( $plugins->getInstalledPluginFiles() as $pluginFile ) {
				if ( $dir === \dirname( $pluginFile ) ) {
					$asset = $plugins->getPluginAsVo( $pluginFile );
					break;
				}
			}
			self::$pluginsByDir[ $dir ] = $asset;
		}
		return self::$pluginsByDir[ $dir ];
	}

	private function themeFromDir( string $dir ) :?WpThemeVo {
		if ( !\array_key_exists( $dir, self::$themesByDir ) ) {
			$asset = null;
			$themes = Services::WpThemes();
			foreach ( $themes->getThemes() as $theme ) {
				if ( $dir === $theme->get_stylesheet() ) {
					$asset = $themes->getThemeAsVo( $dir );
					break;
				}
			}
			self::$themesByDir[ $dir ] = $asset;
		}
		return self::$themesByDir[ $dir ];
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 * @throws NonAssetFileException
	 */
	private function assetFromContext( AssetFileContext $context ) {
		$asset = $context->assetType === 'plugin' ? $this->pluginFromDir( \dirname( $context->assetKey ) ) : $this->themeFromDir( $context->assetKey );
		if ( !$asset instanceof WpPluginVo && !$asset instanceof WpThemeVo ) {
			throw new NonAssetFileException( 'Not a plugin or theme file path.' );
		}
		return $asset;
	}
}
