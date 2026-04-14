<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

class LegacyPathCompatibilityPlan {

	private const LEGACY_ROOT_RELATIVE_PATH = 'src/lib';

	/** @var string[] */
	private array $sourceDirectoriesToMirror;

	/** @var array<string,string> */
	private array $sourceFilesToCopy;

	/** @var array<string,string> */
	private array $overrideFileMap;

	/** @var string[] */
	private array $vendorPrefixedDirectoriesToMirror;

	/** @var array<string,string> */
	private array $vendorPrefixedFilesToCopy;

	/** @var string[] */
	private array $vendorDirectoriesToMirror;

	/** @var array<string,string> */
	private array $vendorFilesToCopy;

	/**
	 * @param string[]             $sourceDirectoriesToMirror
	 * @param array<string,string> $sourceFilesToCopy
	 * @param array<string,string> $overrideFileMap
	 * @param string[]             $vendorPrefixedDirectoriesToMirror
	 * @param array<string,string> $vendorPrefixedFilesToCopy
	 * @param string[]             $vendorDirectoriesToMirror
	 * @param array<string,string> $vendorFilesToCopy
	 */
	public function __construct(
		array $sourceDirectoriesToMirror = [],
		array $sourceFilesToCopy = [],
		array $overrideFileMap = [],
		array $vendorPrefixedDirectoriesToMirror = [],
		array $vendorPrefixedFilesToCopy = [],
		array $vendorDirectoriesToMirror = [],
		array $vendorFilesToCopy = []
	) {
		$this->sourceDirectoriesToMirror = $this->normalizeRelativePaths( $sourceDirectoriesToMirror );
		$this->sourceFilesToCopy = $this->normalizeFileMap( $sourceFilesToCopy );
		$this->overrideFileMap = $this->normalizeFileMap( $overrideFileMap );
		$this->vendorPrefixedDirectoriesToMirror = $this->normalizeRelativePaths( $vendorPrefixedDirectoriesToMirror );
		$this->vendorPrefixedFilesToCopy = $this->normalizeFileMap( $vendorPrefixedFilesToCopy );
		$this->vendorDirectoriesToMirror = $this->normalizeRelativePaths( $vendorDirectoriesToMirror );
		$this->vendorFilesToCopy = $this->normalizeFileMap( $vendorFilesToCopy );
	}

	public static function current() :self {
		return new self();
	}

	public function hasWork() :bool {
		return !empty( $this->sourceDirectoriesToMirror )
			|| !empty( $this->sourceFilesToCopy )
			|| !empty( $this->overrideFileMap )
			|| !empty( $this->vendorPrefixedDirectoriesToMirror )
			|| !empty( $this->vendorPrefixedFilesToCopy )
			|| !empty( $this->vendorDirectoriesToMirror )
			|| !empty( $this->vendorFilesToCopy );
	}

	public function legacyRootDir( string $targetDir ) :string {
		return Path::join( $targetDir, ...$this->splitPath( self::LEGACY_ROOT_RELATIVE_PATH ) );
	}

	public function legacySourceRootDir( string $targetDir ) :string {
		return Path::join( $this->legacyRootDir( $targetDir ), 'src' );
	}

	public function legacyVendorPrefixedRootDir( string $targetDir ) :string {
		return Path::join( $this->legacyRootDir( $targetDir ), 'vendor_prefixed' );
	}

	public function legacyVendorRootDir( string $targetDir ) :string {
		return Path::join( $this->legacyRootDir( $targetDir ), 'vendor' );
	}

	/**
	 * @return string[]
	 */
	public function sourceDirectoriesToMirror() :array {
		return $this->sourceDirectoriesToMirror;
	}

	/**
	 * @return array<string,string>
	 */
	public function sourceFilesToCopy() :array {
		return $this->sourceFilesToCopy;
	}

	/**
	 * @return array<string,string>
	 */
	public function overrideFileMap() :array {
		return $this->overrideFileMap;
	}

	/**
	 * @return string[]
	 */
	public function vendorPrefixedDirectoriesToMirror() :array {
		return $this->vendorPrefixedDirectoriesToMirror;
	}

	/**
	 * @return array<string,string>
	 */
	public function vendorPrefixedFilesToCopy() :array {
		return $this->vendorPrefixedFilesToCopy;
	}

	/**
	 * @return string[]
	 */
	public function vendorDirectoriesToMirror() :array {
		return $this->vendorDirectoriesToMirror;
	}

	/**
	 * @return array<string,string>
	 */
	public function vendorFilesToCopy() :array {
		return $this->vendorFilesToCopy;
	}

	/**
	 * @return string[]
	 */
	public function expectedDirectoryOutputs( string $targetDir ) :array {
		return \array_values( \array_unique( \array_merge(
			$this->buildExpectedPaths( $this->legacySourceRootDir( $targetDir ), $this->sourceDirectoriesToMirror ),
			$this->buildExpectedPaths( $this->legacyVendorPrefixedRootDir( $targetDir ), $this->vendorPrefixedDirectoriesToMirror ),
			$this->buildExpectedPaths( $this->legacyVendorRootDir( $targetDir ), $this->vendorDirectoriesToMirror )
		) ) );
	}

	/**
	 * @return string[]
	 */
	public function expectedFileOutputs( string $targetDir ) :array {
		return \array_values( \array_unique( \array_merge(
			$this->buildExpectedPaths( $this->legacySourceRootDir( $targetDir ), \array_values( $this->sourceFilesToCopy ) ),
			$this->buildExpectedPaths( $this->legacySourceRootDir( $targetDir ), \array_values( $this->overrideFileMap ) ),
			$this->buildExpectedPaths( $this->legacyVendorPrefixedRootDir( $targetDir ), \array_values( $this->vendorPrefixedFilesToCopy ) ),
			$this->buildExpectedPaths( $this->legacyVendorRootDir( $targetDir ), \array_values( $this->vendorFilesToCopy ) )
		) ) );
	}

	/**
	 * @param string[] $relativePaths
	 * @return string[]
	 */
	private function buildExpectedPaths( string $rootDir, array $relativePaths ) :array {
		return \array_values( \array_map(
			fn( string $relativePath ) :string => Path::join( $rootDir, ...$this->splitPath( $relativePath ) ),
			$relativePaths
		) );
	}

	/**
	 * @param string[] $paths
	 * @return string[]
	 */
	private function normalizeRelativePaths( array $paths ) :array {
		$normalized = \array_filter( \array_map(
			function ( $path ) :?string {
				if ( !\is_string( $path ) ) {
					return null;
				}

				$trimmed = $this->normalizeRelativePath( $path );
				return $trimmed === '' ? null : $trimmed;
			},
			$paths
		) );

		return \array_values( \array_unique( $normalized ) );
	}

	/**
	 * @param array<string,string> $fileMap
	 * @return array<string,string>
	 */
	private function normalizeFileMap( array $fileMap ) :array {
		$normalized = [];

		foreach ( $fileMap as $sourceRelativePath => $legacyRelativePath ) {
			if ( !\is_string( $sourceRelativePath ) || !\is_string( $legacyRelativePath ) ) {
				continue;
			}

			$source = $this->normalizeRelativePath( $sourceRelativePath );
			$dest = $this->normalizeRelativePath( $legacyRelativePath );
			if ( $source === '' || $dest === '' ) {
				continue;
			}

			$normalized[ $source ] = $dest;
		}

		return $normalized;
	}

	private function normalizeRelativePath( string $path ) :string {
		return \trim( \str_replace( '\\', '/', $path ), '/' );
	}

	/**
	 * @return string[]
	 */
	private function splitPath( string $relativePath ) :array {
		return \explode( '/', $this->normalizeRelativePath( $relativePath ) );
	}
}
