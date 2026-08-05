<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\{
	AmbiguousAssetFileException,
	AssetHashesNotFound,
	NonAssetFileException,
	UnrecognisedAssetFile
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Plugin,
	Theme
};

class AssetTrustResolver {

	private const AMBIGUOUS_PLUGIN = '__ambiguous_plugin__';

	private static array $plugins = [];

	/**
	 * @var array<string,list<string>>|null
	 */
	private static ?array $pluginFilesByDir = null;

	private static array $themesByDir = [];

	private static array $contextsByPath = [];

	private static array $currentContextsByPath = [];

	private static array $currentAssetsByPath = [];

	private static array $nonAssetMissesByPath = [];

	private static array $relativePathsByPath = [];

	public static function resetMemoization() :void {
		self::$plugins = [];
		self::$pluginFilesByDir = null;
		self::$themesByDir = [];
		self::$contextsByPath = [];
		self::$currentContextsByPath = [];
		self::$currentAssetsByPath = [];
		self::$nonAssetMissesByPath = [];
		self::$relativePathsByPath = [];
	}

	/**
	 * @return array{hashes:list<string>,trusted_source:bool,comparison_basis:string,asset_type:string,asset_key:string,asset_version:string,relative_path:string}
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashDataForFile( string $path ) :array {
		return $this->getHashDataForContext( $path, $this->resolveContext( $path ) );
	}

	/**
	 * @return array{hashes:list<string>,trusted_source:bool,comparison_basis:string,asset_type:string,asset_key:string,asset_version:string,relative_path:string}
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
			'hashes'         => $hash,
			'trusted_source' => $hashSource[ 'trusted_source' ],
			'comparison_basis' => $hashSource[ 'comparison_basis' ],
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
	 * @throws \Exception
	 */
	public function verifyPath( string $path ) :HashVerificationResult {
		return $this->verifyContext( $path, $this->resolveContext( $path ) );
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function verifyContext( string $path, AssetFileContext $context ) :HashVerificationResult {
		$verified = false;
		$hashData = $this->getHashDataForContext( $path, $context );
		$compare = new CompareFileHash();
		foreach ( $hashData[ 'hashes' ] as $hash ) {
			if ( $compare->isEqual( $path, $hash ) ) {
				$verified = true;
				break;
			}
		}

		return new HashVerificationResult(
			$verified,
			$verified && $hashData[ 'trusted_source' ],
			true,
			$hashData[ 'comparison_basis' ],
			$hashData[ 'asset_type' ],
			$hashData[ 'asset_key' ],
			$hashData[ 'asset_version' ],
			$hashData[ 'relative_path' ]
		);
	}

	/**
	 * @throws NonAssetFileException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function verifyStoredContext( string $path, AssetFileContext $context ) :?HashVerificationResult {
		$cacheKey = wp_normalize_path( $path );
		$currentContext = self::$currentContextsByPath[ $cacheKey ] ?? null;
		$asset = self::$currentAssetsByPath[ $cacheKey ] ?? null;
		if ( !$currentContext instanceof AssetFileContext
			 || ( !$asset instanceof WpPluginVo && !$asset instanceof WpThemeVo )
			 || $currentContext->assetType !== $context->assetType
			 || $currentContext->assetKey !== $context->assetKey
			 || $currentContext->assetVersion !== $context->assetVersion
			 || $currentContext->relativePath !== $context->relativePath ) {
			throw new NonAssetFileException( 'Current plugin or theme context is unavailable.' );
		}

		$source = ( new Retrieve() )->byVOFromStoredSnapshot( $asset );
		if ( \is_null( $source ) ) {
			return null;
		}

		$hashes = $source[ 'hashes' ][ $context->relativePath ]
				  ?? ( $source[ 'hashes' ][ \strtolower( $context->relativePath ) ] ?? null );
		$recognised = !empty( $hashes );
		$verified = false;
		if ( $recognised ) {
			$compare = new CompareFileHash();
			foreach ( $hashes as $hash ) {
				if ( $compare->isEqual( $path, $hash ) ) {
					$verified = true;
					break;
				}
			}
		}

		return new HashVerificationResult(
			$verified,
			$verified && $source[ 'trusted_source' ],
			$recognised,
			$source[ 'comparison_basis' ],
			$context->assetType,
			$context->assetKey,
			$context->assetVersion,
			$context->relativePath
		);
	}

	/**
	 * @throws AmbiguousAssetFileException
	 * @throws NonAssetFileException
	 */
	public function resolveCurrentContext( string $path ) :AssetFileContext {
		$cacheKey = wp_normalize_path( $path );
		if ( isset( self::$currentContextsByPath[ $cacheKey ] ) ) {
			return self::$currentContextsByPath[ $cacheKey ];
		}

		$stableContext = $this->resolveContext( $path );
		$asset = $stableContext->assetType === 'plugin'
			? Services::WpPlugins()->getPluginAsVo( $stableContext->assetKey, true )
			: Services::WpThemes()->getThemeAsVo( $stableContext->assetKey, true );
		if ( ( !$asset instanceof WpPluginVo && !$asset instanceof WpThemeVo )
			 || (string)$asset->asset_type !== $stableContext->assetType
			 || (string)$asset->unique_id !== $stableContext->assetKey ) {
			throw new NonAssetFileException( 'Installed plugin or theme identity changed.' );
		}

		$context = new AssetFileContext(
			$stableContext->assetType,
			$stableContext->assetKey,
			(string)$asset->Version,
			$stableContext->relativePath
		);
		self::$currentContextsByPath[ $cacheKey ] = $context;
		self::$currentAssetsByPath[ $cacheKey ] = $asset;
		return $context;
	}

	/**
	 * @throws AmbiguousAssetFileException
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
	 * @throws AmbiguousAssetFileException
	 * @throws NonAssetFileException
	 */
	private function resolvePluginContext( string $path ) :AssetFileContext {
		if ( !$this->isPathInRoot( $path, WP_PLUGIN_DIR ) ) {
			throw new NonAssetFileException( 'Not a plugin file path.' );
		}

		$pluginFiles = new Plugin\Files();
		$fragment = $pluginFiles->getPluginPathFragmentFromPath( $path );
		if ( !\is_string( $fragment ) ) {
			throw new NonAssetFileException( 'Not a plugin file path.' );
		}

		$separator = \strpos( $fragment, '/' );
		$isRootPlugin = $separator === false;
		$asset = $isRootPlugin
			? $this->pluginFromFile( $fragment )
			: $this->pluginFromDir( \substr( $fragment, 0, $separator ) );
		if ( !$asset instanceof WpPluginVo ) {
			throw new NonAssetFileException( 'Not an installed plugin file path.' );
		}

		return new AssetFileContext(
			'plugin',
			(string)$asset->unique_id,
			(string)$asset->Version,
			$isRootPlugin ? $fragment : $this->relativePath( 'plugin', $path, $fragment )
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

	/**
	 * @throws AmbiguousAssetFileException
	 */
	private function pluginFromDir( string $dir ) :?WpPluginVo {
		$cacheKey = 'dir|'.$dir;
		if ( !\array_key_exists( $cacheKey, self::$plugins ) ) {
			$asset = null;
			$plugins = Services::WpPlugins();
			$candidates = $this->pluginFilesForDir( $dir );

			if ( \count( $candidates ) > 1 ) {
				self::$plugins[ $cacheKey ] = self::AMBIGUOUS_PLUGIN;
				error_log( \sprintf(
					'Shield AFS skipped ambiguous plugin ownership: dir=%s; candidate_count=%d; candidates=%s',
					$dir,
					\count( $candidates ),
					\implode( ',', \array_slice( $candidates, 0, 5 ) )
				) );
			}
			elseif ( \count( $candidates ) === 1 ) {
				$maybeAsset = $plugins->getPluginAsVo( $candidates[ 0 ], true );
				$asset = $maybeAsset instanceof WpPluginVo ? $maybeAsset : null;
				self::$plugins[ $cacheKey ] = $asset;
			}
			else {
				self::$plugins[ $cacheKey ] = null;
			}
		}
		if ( self::$plugins[ $cacheKey ] === self::AMBIGUOUS_PLUGIN ) {
			throw new AmbiguousAssetFileException( \sprintf( 'Multiple installed plugin headers found for directory: %s', $dir ) );
		}
		return self::$plugins[ $cacheKey ] instanceof WpPluginVo ? self::$plugins[ $cacheKey ] : null;
	}

	/**
	 * @return list<string>
	 */
	private function pluginFilesForDir( string $dir ) :array {
		if ( \is_null( self::$pluginFilesByDir ) ) {
			$indexed = [];
			foreach ( Services::WpPlugins()->getInstalledPluginFiles() as $pluginFile ) {
				$indexed[ \dirname( $pluginFile ) ][ $pluginFile ] = $pluginFile;
			}

			self::$pluginFilesByDir = [];
			foreach ( $indexed as $pluginDir => $candidateMap ) {
				$candidates = \array_values( $candidateMap );
				\sort( $candidates, \SORT_STRING );
				self::$pluginFilesByDir[ $pluginDir ] = $candidates;
			}
		}

		return self::$pluginFilesByDir[ $dir ] ?? [];
	}

	private function pluginFromFile( string $file ) :?WpPluginVo {
		$cacheKey = 'file|'.$file;
		if ( !\array_key_exists( $cacheKey, self::$plugins ) ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $file, true );
			self::$plugins[ $cacheKey ] = $asset instanceof WpPluginVo ? $asset : null;
		}
		return self::$plugins[ $cacheKey ];
	}

	private function themeFromDir( string $dir ) :?WpThemeVo {
		if ( !\array_key_exists( $dir, self::$themesByDir ) ) {
			$asset = null;
			$themes = Services::WpThemes();
			foreach ( $themes->getThemes() as $theme ) {
				if ( $dir === $theme->get_stylesheet() ) {
					$maybeAsset = $themes->getThemeAsVo( $dir, true );
					$asset = $maybeAsset instanceof WpThemeVo ? $maybeAsset : null;
					break;
				}
			}
			self::$themesByDir[ $dir ] = $asset;
		}
		return self::$themesByDir[ $dir ];
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 * @throws AmbiguousAssetFileException
	 * @throws NonAssetFileException
	 */
	private function assetFromContext( AssetFileContext $context ) {
		if ( $context->assetType === 'plugin' ) {
			$asset = \dirname( $context->assetKey ) === '.'
				? $this->pluginFromFile( $context->assetKey )
				: $this->pluginFromDir( \dirname( $context->assetKey ) );
		}
		else {
			$asset = $this->themeFromDir( $context->assetKey );
		}
		if ( !$asset instanceof WpPluginVo && !$asset instanceof WpThemeVo ) {
			throw new NonAssetFileException( 'Not a plugin or theme file path.' );
		}
		return $asset;
	}

}
