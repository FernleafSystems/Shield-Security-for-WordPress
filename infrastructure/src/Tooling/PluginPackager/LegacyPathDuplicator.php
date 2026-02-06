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
	 * Only the DB entity directories actually autoloaded during shutdown are included:
	 * - ActivityLogs/Meta: AuditLogger -> LocalDbWriter inserts log + meta records
	 * - BotSignal: BotSignalsController tracks page loads; EventsToSignals checks notbot
	 * - Common: BaseLoadRecordsForIPJoins base class used by LoadIpRules
	 * - CrowdSecSignals: EventsToSignals inserts signal records at shutdown
	 * - Event: StatsWriter commits event counts
	 * - IpRules: ProcessOffense -> AddRule -> IpRuleStatus loads/updates rules
	 * - IPs: IPRecords used by audit, traffic, bot signals, offense processing
	 * - ReqLogs: RequestRecords used by audit and traffic log writers
	 * - UserMeta: BotSignalsRecord reads last_login_at
	 */
	private const SRC_DIRECTORIES_TO_MIRROR = [
		[ 'Controller', 'Config' ],              // OptsHandler::store() at shutdown
		[ 'DBs', 'ActivityLogs' ],               // AuditLogger -> LocalDbWriter
		[ 'DBs', 'ActivityLogsMeta' ],           // AuditLogger -> LocalDbWriter metadata
		[ 'DBs', 'BotSignal' ],                  // BotSignalsController, EventsToSignals
		[ 'DBs', 'Common' ],                     // BaseLoadRecordsForIPJoins
		[ 'DBs', 'CrowdSecSignals' ],            // EventsToSignals signal records
		[ 'DBs', 'Event' ],                      // StatsWriter event counts
		[ 'DBs', 'IpRules' ],                    // ProcessOffense -> AddRule -> IpRuleStatus
		[ 'DBs', 'IPs' ],                        // IPRecords (audit, traffic, bot signals)
		[ 'DBs', 'ReqLogs' ],                    // RequestRecords (audit, traffic writers)
		[ 'DBs', 'UserMeta' ],                   // BotSignalsRecord reads last_login_at
		[ 'Logging' ],                           // All log processors
		[ 'Modules', 'IPs', 'Lib', 'IpRules' ],  // IP rule classes
	];

	/**
	 * Individual source files to copy
	 */
	private const SRC_FILES_TO_COPY = [
		// Controller/Dependencies/Monolog.php - Assessed when creating loggers
		[ 'Controller', 'Dependencies', 'Monolog.php' ],
		// Modules/AuditTrail/Lib/ - Audit logging
		[ 'Modules', 'AuditTrail', 'Lib', 'ActivityLogMessageBuilder.php' ],
		[ 'Modules', 'AuditTrail', 'Lib', 'LogHandlers', 'LocalDbWriter.php' ],
		// Modules/HackGuard/Lib/Snapshots/ - Snapshot checking
		[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'FindAssetsToSnap.php' ],
		[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'HashesStorageDir.php' ],
		[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'Store.php' ],
		[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'StoreAction', 'BaseAction.php' ],
		[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'StoreAction', 'Load.php' ],
		// Modules/IPs/Components/ProcessOffense.php - Offense processing
		[ 'Modules', 'IPs', 'Components', 'ProcessOffense.php' ],
		// Modules/IPs/Lib/Bots/BotSignalsRecord.php - Bot signal recording
		[ 'Modules', 'IPs', 'Lib', 'Bots', 'BotSignalsRecord.php' ],
		// Modules/Traffic/Lib/LogHandlers/LocalDbWriter.php - Traffic DB writing
		[ 'Modules', 'Traffic', 'Lib', 'LogHandlers', 'LocalDbWriter.php' ],
	];

	/**
	 * Vendor prefixed directories to mirror
	 */
	private const VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR = [
		[ 'monolog' ],   // Monolog used at shutdown for logging
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
		[ 'mlocati', 'ip-lib' ],  // IPLib used by AddRule at shutdown
		[ 'composer' ],            // Autoloader metadata
	];

	/**
	 * Standard vendor files to copy
	 */
	private const STD_VENDOR_FILES_TO_COPY = [
		'autoload.php',
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

		// Copy individual source files
		\array_map(
			function ( array $path ) use ( $fs, $srcDir, $legacySrcDir ) :void {
				$destPath = Path::join( $legacySrcDir, ...$path );
				$fs->mkdir( \dirname( $destPath ), 0755 );
				$fs->copy(
					Path::join( $srcDir, ...$path ),
					$destPath
				);
			},
			self::SRC_FILES_TO_COPY
		);

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

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
