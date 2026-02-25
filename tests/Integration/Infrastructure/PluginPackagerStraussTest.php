<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Infrastructure;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PackagerConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PluginPackagerStraussTest extends TestCase {

	use PluginPathsTrait;

	private string $packagePath;
	private string $straussVersion;

	private function packagePathJoin( string ...$parts ) :string {
		return Path::join( $this->packagePath, ...$parts );
	}

	protected function setUp() :void {
		parent::setUp();

		if ( !$this->isTestingPackage() ) {
			$this->markTestSkipped( 'Strauss package tests run only when SHIELD_PACKAGE_PATH points to a built package.' );
		}

		$this->packagePath = $this->getPluginRoot();
		$version = PackagerConfig::getStraussVersion();
		if ( $version === null || $version === '' ) {
			$this->markTestSkipped( 'SHIELD_STRAUSS_VERSION not set and packager config not available.' );
		}
		$this->straussVersion = $version;
	}

	/** @group package-targeted */
	public function testVendorPrefixedExists() :void {
		$prefixed = $this->packagePathJoin( 'vendor_prefixed' );
		$this->assertDirectoryExists( $prefixed, 'vendor_prefixed directory missing' );
		$this->assertFileExists( Path::join( $prefixed, 'autoload.php' ) );
	}

	/** @group package-targeted */
	public function testPackagePathParity() :void {
		$vendorPackages = $this->collectPackagePaths( $this->packagePathJoin( 'vendor' ) );
		$prefixedPackages = $this->collectPackagePaths( $this->packagePathJoin( 'vendor_prefixed' ) );

		$overlap = array_values( array_intersect( $vendorPackages, $prefixedPackages ) );
		$this->assertSame( [], $overlap, 'Packages duplicated between vendor and vendor_prefixed: '.implode( ', ', $overlap ) );

		$requiredPrefixedOnly = [
			'monolog/monolog',
			'twig/twig',
			'crowdsec/capi-client',
		];

		foreach ( $requiredPrefixedOnly as $package ) {
			$this->assertContains(
				$package,
				$prefixedPackages,
				"Required prefixed package missing: {$package}"
			);
			$this->assertNotContains(
				$package,
				$vendorPackages,
				"Prefixed-only package should not exist in vendor: {$package}"
			);
		}
	}

	/** @group package-targeted */
	public function testPrefixedLibrariesPresent() :void {
		$prefixed = $this->packagePathJoin( 'vendor_prefixed' );
		foreach ( [ 'monolog', 'twig', 'crowdsec' ] as $dir ) {
			$this->assertDirectoryExists(
				Path::join( $prefixed, $dir ),
				"Prefixed directory missing: {$dir}"
			);
		}
	}

	/** @group package-targeted */
	public function testUnprefixedRemoved() :void {
		$vendor = $this->packagePathJoin( 'vendor' );
		foreach ( [ 'monolog', 'twig', 'bin' ] as $dir ) {
			$this->assertDirectoryDoesNotExist(
				Path::join( $vendor, $dir ),
				"Unprefixed directory should be removed: {$dir}"
			);
		}
	}

	/** @group package-targeted */
	public function testStraussPharRemoved() :void {
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'strauss.phar' ) );
	}

	/** @group package-targeted */
	public function testAutoloaderSuffixApplied() :void {
		$autoloadReal = $this->packagePathJoin( 'vendor/composer/autoload_real.php' );
		$this->assertFileExists( $autoloadReal );

		$content = file_get_contents( $autoloadReal );
		$this->assertNotFalse( $content );
		$this->assertStringContainsString(
			'ComposerAutoloaderInitShieldPackage',
			(string)$content,
			'Package autoloader must use unique suffix to prevent class name conflicts with source autoloader'
		);
	}

	/** @group package-targeted */
	public function testAutoloadsPruned() :void {
		$composerDir = $this->packagePathJoin( 'vendor/composer' );
		$files = [
			'autoload_files.php',
			'autoload_psr4.php',
			'autoload_static.php',
		];

		foreach ( $files as $file ) {
			$path = Path::join( $composerDir, $file );
			if ( !file_exists( $path ) ) {
				continue;
			}
			$content = file_get_contents( $path );
			$this->assertNotFalse( $content );
			$this->assertStringNotContainsString(
				'/twig/twig/',
				(string)$content,
				"Autoload file should not contain twig references: {$file}"
			);
		}
	}

	/** @group package-targeted */
	public function testPrefixedAutoloadsHaveNoVendorLeaks() :void {
		$autoloadFiles = glob( $this->packagePathJoin( 'vendor_prefixed/autoload*.php' ) ) ?: [];
		$this->assertNotSame( [], $autoloadFiles, 'No prefixed autoload files found to inspect.' );

		$leaks = [];
		foreach ( $autoloadFiles as $file ) {
			$content = file_get_contents( $file );
			$this->assertNotFalse( $content, "Failed reading {$file}" );
			if ( preg_match_all( '#/vendor/(?!prefixed/)#', (string)$content, $matches ) ) {
				$leaks[ basename( $file ) ] = array_values( array_unique( $matches[0] ) );
			}
		}

		$this->assertSame(
			[],
			$leaks,
			'Prefixed autoload files reference unprefixed vendor paths: '.json_encode( $leaks )
		);
	}

	/** @group package-targeted */
	public function testPrefixedAutoloadContainsKeyNamespaces() :void {
		$composerDir = Path::join( $this->packagePath, 'vendor_prefixed', 'composer' );
		$autoloadFiles = [
			'autoload_classmap.php',
			'autoload_psr4.php',
			'autoload_static.php',
		];

		$autoloadContents = [];
		foreach ( $autoloadFiles as $file ) {
			$path = Path::join( $composerDir, $file );
			if ( !file_exists( $path ) ) {
				continue;
			}
			$content = file_get_contents( $path );
			$this->assertNotFalse( $content, "Failed reading {$path}" );
			$autoloadContents[ $file ] = (string)$content;
		}

		$this->assertNotSame( [], $autoloadContents, 'No prefixed composer autoload files found to inspect.' );

		// Note: We search for double-backslashes because the autoload files are PHP source
		// where namespace backslashes are escaped (e.g., 'AptowebDeps\\Monolog\\').
		foreach ( [ 'AptowebDeps\\\\Monolog\\\\', 'AptowebDeps\\\\Twig\\\\', 'AptowebDeps\\\\CrowdSec\\\\' ] as $namespace ) {
			$found = false;
			foreach ( $autoloadContents as $content ) {
				if ( strpos( $content, $namespace ) !== false ) {
					$found = true;
					break;
				}
			}

			$this->assertTrue(
				$found,
				sprintf(
					'Prefixed namespace missing from composer autoload files: %s (checked: %s)',
					$namespace,
					implode( ', ', array_keys( $autoloadContents ) )
				)
			);
		}
	}

	/** @group package-targeted */
	public function testPrefixedAutoloadSmoke() :void {
		$prefixedAutoload = $this->packagePathJoin( 'vendor_prefixed/autoload.php' );
		$vendorAutoload = $this->packagePathJoin( 'vendor/autoload.php' );

		$this->assertFileExists( $prefixedAutoload );
		$this->assertFileExists( $vendorAutoload );

		require_once $prefixedAutoload;
		require_once $vendorAutoload;

		$logger = new \AptowebDeps\Monolog\Logger( 'test' );
		$this->assertInstanceOf( \AptowebDeps\Monolog\Logger::class, $logger );

		$loader = new \AptowebDeps\Twig\Loader\ArrayLoader( [] );
		$env = new \AptowebDeps\Twig\Environment( $loader );
		$this->assertInstanceOf( \AptowebDeps\Twig\Environment::class, $env );

		$crowdSecClass = 'AptowebDeps\\CrowdSec\\CapiClient\\Watcher';
		if ( !class_exists( $crowdSecClass ) ) {
			$psr4Path = $this->packagePathJoin( 'vendor_prefixed/composer/autoload_psr4.php' );
			$namespaces = [];
			if ( file_exists( $psr4Path ) ) {
				$psr4 = require $psr4Path;
				if ( is_array( $psr4 ) ) {
					foreach ( array_keys( $psr4 ) as $ns ) {
						if ( strpos( $ns, 'AptowebDeps\\CrowdSec\\CapiClient\\' ) === 0 ) {
							$namespaces[] = rtrim( $ns, '\\' );
						}
					}
				}
			}
			$hint = $namespaces !== [] ? 'Available CrowdSec namespaces: '.implode( ', ', array_unique( $namespaces ) ) : 'No CrowdSec\\CapiClient namespaces found in prefixed autoload_psr4.';
			$this->fail( "CrowdSec prefixed class missing: {$crowdSecClass}. {$hint}" );
		}

		$this->assertTrue( class_exists( $crowdSecClass ) );
	}

	public function testPackageRuntimeCallSitesArePrefixed() :void {
		$checks = [
			'src/Modules/Traffic/Lib/RequestLogger.php'          => [ 'AptowebDeps\\Monolog\\Logger', 'Monolog\\Logger' ],
			'src/Render/RenderService.php'                       => [ 'AptowebDeps\\Twig\\Environment', 'Twig\\Environment' ],
			'src/Modules/IPs/Lib/CrowdSec/CrowdSecController.php' => [ 'AptowebDeps\\CrowdSec\\CapiClient\\Watcher', 'CrowdSec\\CapiClient\\Watcher' ],
		];

		foreach ( $checks as $relativePath => [ $prefixedNamespace, $sourceNamespace ] ) {
			$content = $this->getPluginFileContents( $relativePath, "Packaged runtime file: {$relativePath}" );
			$this->assertStringContainsString(
				$prefixedNamespace,
				$content,
				"Expected rewritten prefixed call site in package output: {$relativePath}"
			);
			$this->assertStringNotContainsString(
				'use '.$sourceNamespace.';',
				$content,
				"Unprefixed source namespace should not remain as a use-import in package output: {$relativePath}"
			);
		}
	}

	public function testLegacySnapshotOpsAreGuardedWhileRuntimeSourceRemainsActive() :void {
		$legacyDeletePath = $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php' );
		$legacyStorePath = $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php' );
		$runtimeDeletePath = $this->packagePathJoin( 'src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php' );
		$runtimeStorePath = $this->packagePathJoin( 'src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php' );

		$this->assertFileExists( $legacyDeletePath );
		$this->assertFileExists( $legacyStorePath );
		$this->assertFileExists( $runtimeDeletePath );
		$this->assertFileExists( $runtimeStorePath );

		$legacyDelete = (string)\file_get_contents( $legacyDeletePath );
		$legacyStore = (string)\file_get_contents( $legacyStorePath );
		$runtimeDelete = (string)\file_get_contents( $runtimeDeletePath );
		$runtimeStore = (string)\file_get_contents( $runtimeStorePath );

		$this->assertStringContainsString( 'return false;', $legacyDelete );
		$this->assertStringContainsString( 'return false;', $legacyStore );
		$this->assertStringNotContainsString( 'PluginControllerConsumer', $legacyDelete );
		$this->assertStringContainsString( 'public function store( $snapshot ) :bool', $legacyStore );
		$this->assertStringNotContainsString( 'SnapshotVO', $legacyStore );
		$this->assertStringNotContainsString( 'PluginControllerConsumer', $legacyStore );
		$this->assertStringNotContainsString( 'return false;', $runtimeDelete );
		$this->assertStringNotContainsString( 'return false;', $runtimeStore );
		$this->assertStringContainsString( 'filterBySlug( $slug )->query()', $runtimeDelete );
		$this->assertStringContainsString( '->insert( Convert::SnapToRecord( $snapshot ) )', $runtimeStore );
	}

	public function testLegacySecurityAdminLoginBoxIsGuardedWhileRuntimeSourceRenders() :void {
		$legacyLoginPath = $this->packagePathJoin( 'src/lib/src/ActionRouter/Actions/Render/Components/FormSecurityAdminLoginBox.php' );
		$runtimeLoginPath = $this->packagePathJoin( 'src/ActionRouter/Actions/Render/Components/FormSecurityAdminLoginBox.php' );
		$legacyActionExceptionPath = $this->packagePathJoin( 'src/lib/src/ActionRouter/Exceptions/ActionException.php' );
		$legacySecurityAdminNotRequiredPath = $this->packagePathJoin( 'src/lib/src/ActionRouter/Actions/Traits/SecurityAdminNotRequired.php' );

		$this->assertFileExists( $legacyLoginPath );
		$this->assertFileExists( $runtimeLoginPath );
		$this->assertFileDoesNotExist( $legacyActionExceptionPath );
		$this->assertFileDoesNotExist( $legacySecurityAdminNotRequiredPath );

		$legacyLogin = (string)\file_get_contents( $legacyLoginPath );
		$runtimeLogin = (string)\file_get_contents( $runtimeLoginPath );

		$this->assertStringContainsString( 'extends BaseAction', $legacyLogin );
		$this->assertStringContainsString( 'protected function checkAccess()', $legacyLogin );
		$this->assertStringContainsString( "'render_output' => ''", $legacyLogin );
		$this->assertStringContainsString( "'html'          => ''", $legacyLogin );
		$this->assertStringNotContainsString( 'extends BaseRender', $legacyLogin );
		$this->assertStringNotContainsString( 'SecurityAdminNotRequired', $legacyLogin );
		$this->assertStringNotContainsString( 'ActionException', $legacyLogin );
		$this->assertStringContainsString( 'extends \\FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\BaseRender', $runtimeLogin );
	}

	public function testLegacyMonologAndSnapshotFinderAreGuardedWhileRuntimeSourceRemainsActive() :void {
		$legacyMonologPath = $this->packagePathJoin( 'src/lib/src/Controller/Dependencies/Monolog.php' );
		$runtimeMonologPath = $this->packagePathJoin( 'src/Controller/Dependencies/Monolog.php' );
		$legacyFindAssetsPath = $this->packagePathJoin( 'src/lib/src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php' );
		$runtimeFindAssetsPath = $this->packagePathJoin( 'src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php' );

		$legacyMonolog = $this->getPluginFileContents(
			'src/lib/src/Controller/Dependencies/Monolog.php',
			$legacyMonologPath
		);
		$runtimeMonolog = $this->getPluginFileContents(
			'src/Controller/Dependencies/Monolog.php',
			$runtimeMonologPath
		);
		$legacyFindAssets = $this->getPluginFileContents(
			'src/lib/src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php',
			$legacyFindAssetsPath
		);
		$runtimeFindAssets = $this->getPluginFileContents(
			'src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php',
			$runtimeFindAssetsPath
		);

		$this->assertStringContainsString( "throw new \\Exception( 'Legacy shutdown guard: monolog disabled.' );", $legacyMonolog );
		$this->assertStringNotContainsString( 'includePrefixedVendor()', $legacyMonolog );
		$this->assertStringContainsString( 'ClassDependencyGuard', $runtimeMonolog );
		$this->assertStringContainsString( 'AptowebDeps\\Monolog\\Logger', $runtimeMonolog );

		$this->assertStringContainsString( 'return [];', $legacyFindAssets );
		$this->assertStringNotContainsString( 'Services::WpPlugins()->getPluginsAsVo()', $legacyFindAssets );
		$this->assertStringContainsString( 'Services::WpPlugins()->getPluginsAsVo()', $runtimeFindAssets );
	}

	public function testLegacyIpOffenseAndBotSignalGuardsAreAppliedWhileRuntimeSourceRemainsActive() :void {
		$legacyProcessOffensePath = $this->packagePathJoin( 'src/lib/src/Modules/IPs/Components/ProcessOffense.php' );
		$runtimeProcessOffensePath = $this->packagePathJoin( 'src/Modules/IPs/Components/ProcessOffense.php' );
		$legacyBotSignalsPath = $this->packagePathJoin( 'src/lib/src/Modules/IPs/Lib/Bots/BotSignalsRecord.php' );
		$runtimeBotSignalsPath = $this->packagePathJoin( 'src/Modules/IPs/Lib/Bots/BotSignalsRecord.php' );
		$legacyDbBotSignalRecordPath = $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/BotSignalRecord.php' );
		$runtimeDbBotSignalRecordPath = $this->packagePathJoin( 'src/DBs/BotSignal/BotSignalRecord.php' );

		$this->assertFileExists( $legacyProcessOffensePath );
		$this->assertFileExists( $runtimeProcessOffensePath );
		$this->assertFileExists( $legacyBotSignalsPath );
		$this->assertFileExists( $runtimeBotSignalsPath );
		$this->assertFileExists( $legacyDbBotSignalRecordPath );
		$this->assertFileExists( $runtimeDbBotSignalRecordPath );

		$legacyProcessOffense = (string)\file_get_contents( $legacyProcessOffensePath );
		$runtimeProcessOffense = (string)\file_get_contents( $runtimeProcessOffensePath );
		$legacyBotSignals = (string)\file_get_contents( $legacyBotSignalsPath );
		$runtimeBotSignals = (string)\file_get_contents( $runtimeBotSignalsPath );
		$legacyDbBotSignalRecord = (string)\file_get_contents( $legacyDbBotSignalRecordPath );
		$runtimeDbBotSignalRecord = (string)\file_get_contents( $runtimeDbBotSignalRecordPath );

		$this->assertStringContainsString( 'public function incrementOffenses', $legacyProcessOffense );
		$this->assertStringNotContainsString( 'new AddRule()', $legacyProcessOffense );
		$this->assertStringNotContainsString( 'IpRulesCache::Delete', $legacyProcessOffense );
		$this->assertStringContainsString( 'new AddRule()', $runtimeProcessOffense );
		$this->assertStringContainsString( 'IpRulesCache::Delete', $runtimeProcessOffense );

		$this->assertStringContainsString( 'public function retrieve() :BotSignalRecord', $legacyBotSignals );
		$this->assertStringContainsString( "'notbot_at'", $legacyBotSignals );
		$this->assertStringNotContainsString( 'IpRuleStatus', $legacyBotSignals );
		$this->assertStringNotContainsString( 'IPRecords', $legacyBotSignals );
		$this->assertStringNotContainsString( 'UserMeta', $legacyBotSignals );
		$this->assertStringContainsString( 'IpRuleStatus', $runtimeBotSignals );
		$this->assertStringContainsString( 'IPRecords', $runtimeBotSignals );
		$this->assertStringContainsString( 'UserMeta', $runtimeBotSignals );

		$this->assertStringContainsString( 'class BotSignalRecord', $legacyDbBotSignalRecord );
		$this->assertStringContainsString( 'public function applyFromArray', $legacyDbBotSignalRecord );
		$this->assertStringNotContainsString( 'extends Ops\Record', $legacyDbBotSignalRecord );
		$this->assertStringContainsString( 'extends Ops\Record', $runtimeDbBotSignalRecord );
		$this->assertStringNotContainsString( 'public function applyFromArray', $runtimeDbBotSignalRecord );
	}

	public function testLegacyEventAndCrowdSecDbHandlersAreGuardedWhileRuntimeSourceRemainsActive() :void {
		$legacyEventHandlerPath = $this->packagePathJoin( 'src/lib/src/DBs/Event/Ops/Handler.php' );
		$runtimeEventHandlerPath = $this->packagePathJoin( 'src/DBs/Event/Ops/Handler.php' );
		$legacyCrowdSecHandlerPath = $this->packagePathJoin( 'src/lib/src/DBs/CrowdSecSignals/Ops/Handler.php' );
		$runtimeCrowdSecHandlerPath = $this->packagePathJoin( 'src/DBs/CrowdSecSignals/Ops/Handler.php' );

		$this->assertFileExists( $legacyEventHandlerPath );
		$this->assertFileExists( $runtimeEventHandlerPath );
		$this->assertFileExists( $legacyCrowdSecHandlerPath );
		$this->assertFileExists( $runtimeCrowdSecHandlerPath );

		$legacyEventHandler = (string)\file_get_contents( $legacyEventHandlerPath );
		$runtimeEventHandler = (string)\file_get_contents( $runtimeEventHandlerPath );
		$legacyCrowdSecHandler = (string)\file_get_contents( $legacyCrowdSecHandlerPath );
		$runtimeCrowdSecHandler = (string)\file_get_contents( $runtimeCrowdSecHandlerPath );

		$this->assertStringContainsString( 'public function isReady() :bool', $legacyEventHandler );
		$this->assertStringContainsString( 'public function commitEvents', $legacyEventHandler );
		$this->assertStringContainsString( 'return false;', $legacyEventHandler );
		$this->assertStringNotContainsString(
			'extends \\FernleafSystems\\Wordpress\\Plugin\\Core\\Databases\\Base\\Handler',
			$legacyEventHandler
		);
		$this->assertStringContainsString(
			'extends \\FernleafSystems\\Wordpress\\Plugin\\Core\\Databases\\Base\\Handler',
			$runtimeEventHandler
		);
		$this->assertStringContainsString( 'commitEvents', $runtimeEventHandler );

		$this->assertStringContainsString( 'public function getRecord() :LegacyRecordStub', $legacyCrowdSecHandler );
		$this->assertStringContainsString( 'public function getQueryInserter() :LegacyInserterStub', $legacyCrowdSecHandler );
		$this->assertStringContainsString( 'class LegacyRecordStub', $legacyCrowdSecHandler );
		$this->assertStringNotContainsString(
			'extends \\FernleafSystems\\Wordpress\\Plugin\\Core\\Databases\\Base\\Handler',
			$legacyCrowdSecHandler
		);
		$this->assertStringContainsString(
			'extends \\FernleafSystems\\Wordpress\\Plugin\\Core\\Databases\\Base\\Handler',
			$runtimeCrowdSecHandler
		);
	}

	public function testLegacyPrunedShutdownPathsAreNotDuplicated() :void {
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/ActivityLogs' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/ActivityLogsMeta' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/ReqLogs' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/Logging' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/vendor_prefixed/monolog' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/IpRules' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/IPs/Lib/IpRules' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/UserMeta' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/IPs' ) );
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib/vendor/mlocati/ip-lib' ) );

		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Events/EventStrings.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/ActivityLogMessageBuilder.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/LogHandlers/LocalDbWriter.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/HackGuard/Lib/Snapshots/HashesStorageDir.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/HackGuard/Lib/Snapshots/Store.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/HackGuard/Lib/Snapshots/StoreAction/BaseAction.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/HackGuard/Lib/Snapshots/StoreAction/Load.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/Traffic/Lib/LogHandlers/LocalDbWriter.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/Event/Ops/Insert.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/Event/Ops/Record.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/CrowdSecSignals/Ops/Insert.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/CrowdSecSignals/Ops/Record.php' ) );

		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/Common/BaseLoadRecordsForIPJoins.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/Snapshots/Ops/Handler.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/Snapshots/Ops/Record.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/Snapshots/Ops/Insert.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/LoadBotSignalRecords.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/Ops/Handler.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/Ops/Record.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/Ops/Insert.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/Ops/Delete.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/Ops/Select.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/ActionRouter/Exceptions/ActionException.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/ActionRouter/Actions/Traits/SecurityAdminNotRequired.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/SnapshotVO.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Build.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Convert.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Diff.php' ) );
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Retrieve.php' ) );
		$this->assertFileExists( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php' ) );
		$this->assertFileExists( $this->packagePathJoin( 'src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php' ) );
		$this->assertFileExists( $this->packagePathJoin( 'src/lib/src/DBs/BotSignal/BotSignalRecord.php' ) );
	}

	/**
	 * @return string[]
	 */
	private function collectPackagePaths( string $baseDir ) :array {
		if ( !is_dir( $baseDir ) ) {
			return [];
		}

		$packages = [];
		foreach ( scandir( $baseDir ) ?: [] as $vendor ) {
			if ( $vendor === '.' || $vendor === '..' ) {
				continue;
			}
			$vendorPath = Path::join( $baseDir, $vendor );
			if ( !is_dir( $vendorPath ) ) {
				continue;
			}
			foreach ( scandir( $vendorPath ) ?: [] as $package ) {
				if ( $package === '.' || $package === '..' ) {
					continue;
				}
				$packagePath = Path::join( $vendorPath, $package );
				if ( is_dir( $packagePath ) ) {
					$packages[] = "{$vendor}/{$package}";
				}
			}
		}

		sort( $packages );
		return array_values( array_unique( $packages ) );
	}
}
