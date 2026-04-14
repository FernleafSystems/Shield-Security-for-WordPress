<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Creates legacy path duplicates for upgrade compatibility.
 *
 * During WordPress plugin upgrades, the old autoloader is still loaded in memory
 * with the old PSR-4 paths. When shutdown hooks fire, PHP tries to autoload classes
 * from the old paths but the new files are on disk. This class duplicates selected
 * files to their legacy paths when a compatibility plan is active.
 */
class LegacyPathDuplicator {

	private LegacyPathCompatibilityPlan $plan;

	/** @var callable */
	private $logger;

	private string $overridesRootDir;

	public function __construct(
		LegacyPathCompatibilityPlan $plan,
		callable $logger,
		?string $overridesRootDir = null
	) {
		$this->plan = $plan;
		$this->logger = $logger;
		$this->overridesRootDir = $overridesRootDir ?? Path::join( __DIR__, 'LegacyOverrides', 'src' );
	}

	public function createDuplicates( string $targetDir ) :void {
		$fs = new Filesystem();
		$legacyRootDir = $this->plan->legacyRootDir( $targetDir );

		if ( \file_exists( $legacyRootDir ) ) {
			$fs->remove( $legacyRootDir );
		}

		if ( !$this->plan->hasWork() ) {
			$this->log( 'No active legacy path duplicates configured for this release.' );
			return;
		}

		$this->log( 'Creating legacy path duplicates for upgrade compatibility...' );

		$this->mirrorRelativeDirectories(
			$fs,
			Path::join( $targetDir, 'src' ),
			$this->plan->legacySourceRootDir( $targetDir ),
			$this->plan->sourceDirectoriesToMirror()
		);
		$this->copyMappedFiles(
			$fs,
			Path::join( $targetDir, 'src' ),
			$this->plan->legacySourceRootDir( $targetDir ),
			$this->plan->sourceFilesToCopy()
		);
		$this->applyLegacyOverrides( $fs, $this->plan->legacySourceRootDir( $targetDir ) );

		$this->mirrorRelativeDirectories(
			$fs,
			Path::join( $targetDir, 'vendor_prefixed' ),
			$this->plan->legacyVendorPrefixedRootDir( $targetDir ),
			$this->plan->vendorPrefixedDirectoriesToMirror()
		);
		$this->copyMappedFiles(
			$fs,
			Path::join( $targetDir, 'vendor_prefixed' ),
			$this->plan->legacyVendorPrefixedRootDir( $targetDir ),
			$this->plan->vendorPrefixedFilesToCopy()
		);

		$this->mirrorRelativeDirectories(
			$fs,
			Path::join( $targetDir, 'vendor' ),
			$this->plan->legacyVendorRootDir( $targetDir ),
			$this->plan->vendorDirectoriesToMirror()
		);
		$this->copyMappedFiles(
			$fs,
			Path::join( $targetDir, 'vendor' ),
			$this->plan->legacyVendorRootDir( $targetDir ),
			$this->plan->vendorFilesToCopy()
		);

		$this->log( '  ✓ Created legacy path duplicates for upgrade compatibility' );
	}

	private function applyLegacyOverrides( Filesystem $fs, string $legacySrcDir ) :void {
		foreach ( $this->plan->overrideFileMap() as $sourceRelativePath => $legacyRelativePath ) {
			$sourcePath = Path::join( $this->overridesRootDir, ...\explode( '/', $sourceRelativePath ) );
			if ( !\is_file( $sourcePath ) ) {
				throw new \RuntimeException( \sprintf( 'Required legacy override missing: %s', $sourcePath ) );
			}

			$destPath = Path::join( $legacySrcDir, ...\explode( '/', $legacyRelativePath ) );
			$fs->mkdir( \dirname( $destPath ), 0755 );
			$fs->copy( $sourcePath, $destPath, true );
		}
	}

	/**
	 * @param string[] $relativePaths
	 */
	private function mirrorRelativeDirectories(
		Filesystem $fs,
		string $sourceRootDir,
		string $destRootDir,
		array $relativePaths
	) :void {
		foreach ( $relativePaths as $relativePath ) {
			$src = Path::join( $sourceRootDir, ...\explode( '/', $relativePath ) );
			if ( !\is_dir( $src ) ) {
				throw new \RuntimeException( \sprintf( 'Required legacy source directory missing: %s', $src ) );
			}

			$dest = Path::join( $destRootDir, ...\explode( '/', $relativePath ) );
			$fs->mirror( $src, $dest );
		}
	}

	/**
	 * @param array<string,string> $fileMap
	 */
	private function copyMappedFiles(
		Filesystem $fs,
		string $sourceRootDir,
		string $destRootDir,
		array $fileMap
	) :void {
		foreach ( $fileMap as $sourceRelativePath => $destRelativePath ) {
			$src = Path::join( $sourceRootDir, ...\explode( '/', $sourceRelativePath ) );
			if ( !\is_file( $src ) ) {
				throw new \RuntimeException( \sprintf( 'Required legacy source file missing: %s', $src ) );
			}

			$dest = Path::join( $destRootDir, ...\explode( '/', $destRelativePath ) );
			$fs->mkdir( \dirname( $dest ), 0755 );
			$fs->copy( $src, $dest, true );
		}
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
