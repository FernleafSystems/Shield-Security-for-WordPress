<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Creates legacy path duplicates for upgrade compatibility.
 *
 * During WordPress plugin upgrades, the old autoloader is still loaded in memory
 * with the old PSR-4 paths. When shutdown hooks fire, PHP tries to autoload classes
 * from the OLD paths but the NEW files are on disk. This class duplicates the classes
 * that are autoloaded during shutdown to their legacy paths.
 */
class LegacyPathDuplicator {

	/**
	 * Source directories to mirror (full copy).
	 *
	 * Only directories still required for late autoload/shutdown compatibility are included.
	 */
	private const SRC_DIRECTORIES_TO_MIRROR = [
		[ 'Controller', 'Config' ],              // OptsHandler::store() at shutdown
	];
	/**
	 * Explicit legacy override source -> destination mappings.
	 *
	 * @var array<string,string>
	 */
	private const LEGACY_OVERRIDE_FILE_MAP = [
		'Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php'                      => 'Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php',
		'Modules/AuditTrail/Lib/Snapshots/Ops/Store.php'                       => 'Modules/AuditTrail/Lib/Snapshots/Ops/Store.php',
		'ActionRouter/Actions/Render/Components/FormSecurityAdminLoginBox.php' => 'ActionRouter/Actions/Render/Components/FormSecurityAdminLoginBox.php',
		'Controller/Dependencies/Monolog.php'                                  => 'Controller/Dependencies/Monolog.php',
		'Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php'                 => 'Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php',
		'Modules/IPs/Components/ProcessOffense.php'                            => 'Modules/IPs/Components/ProcessOffense.php',
		'Modules/IPs/Lib/Bots/BotSignalsRecord.php'                            => 'Modules/IPs/Lib/Bots/BotSignalsRecord.php',
		'DBs/BotSignal/BotSignalRecord.php'                                    => 'DBs/BotSignal/BotSignalRecord.php',
		'DBs/Event/Ops/Handler.php'                                            => 'DBs/Event/Ops/Handler.php',
		'DBs/CrowdSecSignals/Ops/Handler.php'                                  => 'DBs/CrowdSecSignals/Ops/Handler.php',
		'Rules/Build/Builder.php'                                              => 'Rules/Build/Builder.php',
	];
	/**
	 * Vendor prefixed directories to mirror
	 */
	private const VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR = [
		[ 'composer' ],  // Autoloader metadata
	];
	/**
	 * Vendor prefixed files to copy
	 */
	private const VENDOR_PREFIXED_FILES_TO_COPY = [
		'autoload.php',
		'autoload-classmap.php',
	];
	/**
	 * Standard vendor directories to mirror (for shutdown compatibility)
	 */
	private const STD_VENDOR_DIRECTORIES_TO_MIRROR = [
		[ 'fernleafsystems', 'wordpress-services', 'src' ], // Used for beta upgrades handling
		[ 'composer' ],            // Autoloader metadata
	];
	/**
	 * Standard vendor files to copy
	 */
	private const STD_VENDOR_FILES_TO_COPY = [
	];

	/** @var callable */
	private $logger;

	public function __construct( callable $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Create legacy path duplicates for upgrade compatibility.
	 */
	public function createDuplicates( string $targetDir ) :void {
		$this->log( 'Creating legacy path duplicates for upgrade compatibility...' );

		$fs = new Filesystem();
		$srcDir = Path::join( $targetDir, 'src' );
		$legacySrcDir = Path::join( $targetDir, 'src', 'lib', 'src' );
		$vendorPrefixedDir = Path::join( $targetDir, 'vendor_prefixed' );
		$legacyVendorPrefixedDir = Path::join( $targetDir, 'src', 'lib', 'vendor_prefixed' );
		$vendorDir = Path::join( $targetDir, 'vendor' );
		$legacyVendorDir = Path::join( $targetDir, 'src', 'lib', 'vendor' );

		// Create legacy directory structure
		$fs->mkdir( $legacySrcDir, 0755 );
		$fs->mkdir( $legacyVendorPrefixedDir, 0755 );
		$fs->mkdir( $legacyVendorDir, 0755 );

		// Mirror source directories
		\array_map(
			fn( array $path ) => $fs->mirror(
				Path::join( $srcDir, ...$path ),
				Path::join( $legacySrcDir, ...$path )
			),
			self::SRC_DIRECTORIES_TO_MIRROR
		);

		// Apply known legacy shutdown overrides without affecting runtime source files.
		$this->applyLegacyOverrides( $legacySrcDir );

		// Mirror vendor prefixed directories
		\array_map(
			fn( array $path ) => $fs->mirror(
				Path::join( $vendorPrefixedDir, ...$path ),
				Path::join( $legacyVendorPrefixedDir, ...$path )
			),
			self::VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR
		);

		// Copy vendor prefixed files
		\array_map(
			function ( string $file ) use ( $fs, $vendorPrefixedDir, $legacyVendorPrefixedDir ) :void {
				$src = Path::join( $vendorPrefixedDir, $file );
				if ( \file_exists( $src ) ) {
					$fs->copy( $src, Path::join( $legacyVendorPrefixedDir, $file ) );
				}
			},
			self::VENDOR_PREFIXED_FILES_TO_COPY
		);

		// Mirror standard vendor directories
		\array_map(
			fn( array $path ) => $fs->mirror(
				Path::join( $vendorDir, ...$path ),
				Path::join( $legacyVendorDir, ...$path )
			),
			self::STD_VENDOR_DIRECTORIES_TO_MIRROR
		);

		// Copy standard vendor files
		\array_map(
			function ( string $file ) use ( $fs, $vendorDir, $legacyVendorDir ) :void {
				$src = Path::join( $vendorDir, $file );
				if ( \file_exists( $src ) ) {
					$fs->copy( $src, Path::join( $legacyVendorDir, $file ) );
				}
			},
			self::STD_VENDOR_FILES_TO_COPY
		);

		$this->log( '  ✓ Created legacy path duplicates for upgrade compatibility' );
	}

	private function applyLegacyOverrides( string $legacySrcDir ) :void {
		$fs = new Filesystem();
		$overridesRoot = $this->getLegacyOverridesRootDir();

		foreach ( self::LEGACY_OVERRIDE_FILE_MAP as $sourceRelativePath => $legacyRelativePath ) {
			$sourcePath = Path::join( $overridesRoot, ...\explode( '/', $sourceRelativePath ) );
			if ( !\file_exists( $sourcePath ) ) {
				throw new \RuntimeException( \sprintf( 'Required legacy override missing: %s', $sourcePath ) );
			}

			$destPath = Path::join( $legacySrcDir, ...\explode( '/', $legacyRelativePath ) );
			$fs->mkdir( \dirname( $destPath ), 0755 );
			$fs->copy( $sourcePath, $destPath, true );
		}
	}

	protected function getLegacyOverridesRootDir() :string {
		return Path::join( __DIR__, 'LegacyOverrides/src' );
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
