<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathDuplicator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for LegacyPathDuplicator.
 * Tests directory mirroring and file copying for upgrade compatibility.
 */
class LegacyPathDuplicatorTest extends TestCase {

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = sys_get_temp_dir().'/shield-test-'.uniqid();
		$this->fs->mkdir( $this->tempDir );
	}

	protected function tearDown() :void {
		if ( is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
		parent::tearDown();
	}

	private function createDuplicator( ?callable $logger = null ) :LegacyPathDuplicator {
		return new LegacyPathDuplicator( $logger ?? function () {} );
	}

	private function getConstant( string $name ) {
		$reflection = new ReflectionClass( LegacyPathDuplicator::class );
		return $reflection->getConstant( $name );
	}

	private function getSrcFilesToCopy() :array {
		$filesToCopy = $this->getConstant( 'SRC_FILES_TO_COPY' );
		return \is_array( $filesToCopy ) ? $filesToCopy : [];
	}

	private function setupMinimalPackageStructure() :void {
		// Source directories to mirror - create dir + dummy file so mirror has content
		foreach ( $this->getConstant( 'SRC_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $this->tempDir.'/src/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/'.\end( $pathParts ).'Test.php', '<?php' );
		}

		// Individual source files to copy
		foreach ( $this->getSrcFilesToCopy() as $pathParts ) {
			$filePath = $this->tempDir.'/src/'.\implode( '/', $pathParts );
			$this->fs->mkdir( \dirname( $filePath ) );
			$this->fs->dumpFile( $filePath, '<?php' );
		}

		$this->seedLegacyOverrideTargets();
		$this->seedPrunedRuntimeOnlySources();

		// Vendor prefixed directories to mirror - create dir + dummy file
		foreach ( $this->getConstant( 'VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $this->tempDir.'/vendor_prefixed/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php', '<?php' );
		}

		// Vendor prefixed files to copy
		foreach ( $this->getConstant( 'VENDOR_PREFIXED_FILES_TO_COPY' ) as $file ) {
			$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/'.$file, '<?php' );
		}

		// Standard vendor directories to mirror - create dir + dummy file
		foreach ( $this->getConstant( 'STD_VENDOR_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $this->tempDir.'/vendor/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php', '<?php' );
		}

		// Standard vendor files to copy
		foreach ( $this->getConstant( 'STD_VENDOR_FILES_TO_COPY' ) as $file ) {
			$this->fs->dumpFile( $this->tempDir.'/vendor/'.$file, '<?php' );
		}
	}

	private function seedLegacyOverrideTargets() :void {
		$deletePath = $this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php';
		$this->fs->mkdir( \dirname( $deletePath ) );
		$this->fs->dumpFile( $deletePath, <<<'PHP'
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops as SnapshotDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Delete {

	use PluginControllerConsumer;

	public function delete( string $slug ) :bool {
		/** @var SnapshotDB\Delete $deleter */
		$deleter = self::con()->db_con->activity_snapshots->getQueryDeleter();
		return $deleter->filterBySlug( $slug )->query();
	}
}
PHP
		);

		$storePath = $this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php';
		$this->fs->mkdir( \dirname( $storePath ) );
		$this->fs->dumpFile( $storePath, <<<'PHP'
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Store {

	use PluginControllerConsumer;

	public function store( SnapshotVO $snapshot ) :bool {
		return self::con()
			->db_con
			->activity_snapshots
			->getQueryInserter()
			->insert( Convert::SnapToRecord( $snapshot ) );
	}
}
PHP
		);

		$loginBoxPath = $this->tempDir.'/src/ActionRouter/Actions/Render/Components/FormSecurityAdminLoginBox.php';
		$this->fs->mkdir( \dirname( $loginBoxPath ) );
		$this->fs->dumpFile( $loginBoxPath, <<<'PHP'
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class FormSecurityAdminLoginBox extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_form_security_admin_loginbox';
	public const TEMPLATE = '/components/security_admin/login_box.twig';

	protected function getRenderData() :array {
		return [
			'flags'   => [
				'restrict_options' => self::con()->opts->optIs( 'admin_access_restrict_options', 'Y' ),
			],
			'strings' => [
				'access_message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
				'access_submit_label' => __( 'Go!', 'wp-simple-firewall' ),
			],
		];
	}
}
PHP
		);
	}

	private function seedPrunedRuntimeOnlySources() :void {
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/Common/BaseLoadRecordsForIPJoins.php',
			'<?php declare( strict_types=1 ); class BaseLoadRecordsForIPJoins {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/Snapshots/Ops/Handler.php',
			'<?php declare( strict_types=1 ); class Handler {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/Snapshots/Ops/Record.php',
			'<?php declare( strict_types=1 ); class Record {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/SnapshotVO.php',
			'<?php declare( strict_types=1 ); class SnapshotVO {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Build.php',
			'<?php declare( strict_types=1 ); class Build {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Convert.php',
			'<?php declare( strict_types=1 ); class Convert {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Diff.php',
			'<?php declare( strict_types=1 ); class Diff {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Retrieve.php',
			'<?php declare( strict_types=1 ); class Retrieve {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/BotSignalRecord.php',
			'<?php declare( strict_types=1 ); class BotSignalRecord extends Ops\Record {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/LoadBotSignalRecords.php',
			'<?php declare( strict_types=1 ); class LoadBotSignalRecords {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/Ops/Record.php',
			'<?php declare( strict_types=1 ); class Record {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/Ops/Handler.php',
			'<?php declare( strict_types=1 ); class Handler {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/Ops/Insert.php',
			'<?php declare( strict_types=1 ); class Insert {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/Ops/Delete.php',
			'<?php declare( strict_types=1 ); class Delete {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/Ops/Select.php',
			'<?php declare( strict_types=1 ); class Select {}'
		);
		$this->fs->dumpFile(
			$this->tempDir.'/src/DBs/BotSignal/Ops/Common.php',
			'<?php declare( strict_types=1 ); trait Common {}'
		);
	}

	// =========================================================================
	// createDuplicates() tests
	// =========================================================================

	public function testCreateDuplicatesCreatesLegacyDirectoryStructure() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check legacy directories exist
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/src' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor_prefixed' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor' );
	}

	public function testCreateDuplicatesMirrorsSourceDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'SRC_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$legacyPath = $this->tempDir.'/src/lib/src/'.\implode( '/', $pathParts );
			$this->assertDirectoryExists( $legacyPath );
			$this->assertFileExists( $legacyPath.'/'.\end( $pathParts ).'Test.php' );
		}
	}

	public function testCreateDuplicatesCopiesIndividualSourceFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		$filesToCopy = $this->getSrcFilesToCopy();
		$this->assertIsArray( $filesToCopy );

		foreach ( $filesToCopy as $pathParts ) {
			$this->assertFileExists(
				$this->tempDir.'/src/lib/src/'.\implode( '/', $pathParts )
			);
		}
	}

	public function testCreateDuplicatesMirrorsVendorPrefixedDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$legacyPath = $this->tempDir.'/src/lib/vendor_prefixed/'.\implode( '/', $pathParts );
			$this->assertDirectoryExists( $legacyPath );
			$this->assertFileExists( $legacyPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php' );
		}
	}

	public function testCreateDuplicatesCopiesVendorPrefixedFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'VENDOR_PREFIXED_FILES_TO_COPY' ) as $file ) {
			$this->assertFileExists( $this->tempDir.'/src/lib/vendor_prefixed/'.$file );
		}
	}

	public function testCreateDuplicatesMirrorsStandardVendorDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'STD_VENDOR_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$legacyPath = $this->tempDir.'/src/lib/vendor/'.\implode( '/', $pathParts );
			$this->assertDirectoryExists( $legacyPath );
			$this->assertFileExists( $legacyPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php' );
		}
	}

	public function testCreateDuplicatesCopiesStandardVendorFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'STD_VENDOR_FILES_TO_COPY' ) as $file ) {
			$this->assertFileExists( $this->tempDir.'/src/lib/vendor/'.$file );
		}
	}

	public function testCreateDuplicatesAppliesLegacyRuntimeGuards() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		$legacyDelete = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php'
		);
		$this->assertStringContainsString( 'return false;', $legacyDelete );
		$this->assertStringNotContainsString( 'filterBySlug( $slug )->query()', $legacyDelete );
		$this->assertStringNotContainsString( 'PluginControllerConsumer', $legacyDelete );

		$legacyStore = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php'
		);
		$this->assertStringContainsString( 'return false;', $legacyStore );
		$this->assertStringNotContainsString( '->insert( Convert::SnapToRecord( $snapshot ) )', $legacyStore );
		$this->assertStringContainsString( 'public function store( $snapshot ) :bool', $legacyStore );
		$this->assertStringNotContainsString( 'SnapshotVO', $legacyStore );
		$this->assertStringNotContainsString( 'PluginControllerConsumer', $legacyStore );

		$runtimeDelete = (string)\file_get_contents(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php'
		);
		$this->assertStringContainsString( 'filterBySlug( $slug )->query()', $runtimeDelete );
		$this->assertStringNotContainsString( 'return false;', $runtimeDelete );

		$runtimeStore = (string)\file_get_contents(
			$this->tempDir.'/src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php'
		);
		$this->assertStringContainsString( '->insert( Convert::SnapToRecord( $snapshot ) )', $runtimeStore );
		$this->assertStringNotContainsString( 'return false;', $runtimeStore );

		$legacyLoginBox = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/ActionRouter/Actions/Render/Components/FormSecurityAdminLoginBox.php'
		);
		$this->assertStringContainsString( 'extends BaseAction', $legacyLoginBox );
		$this->assertStringContainsString( 'protected function checkAccess()', $legacyLoginBox );
		$this->assertStringContainsString( "'render_output' => ''", $legacyLoginBox );
		$this->assertStringContainsString( "'html'          => ''", $legacyLoginBox );
		$this->assertStringNotContainsString( 'extends BaseRender', $legacyLoginBox );
		$this->assertStringNotContainsString( 'SecurityAdminNotRequired', $legacyLoginBox );
		$this->assertStringNotContainsString( 'ActionException', $legacyLoginBox );

		$legacyMonolog = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/Controller/Dependencies/Monolog.php'
		);
		$this->assertStringContainsString(
			"throw new \\Exception( 'Legacy shutdown guard: monolog disabled.' );",
			$legacyMonolog
		);
		$this->assertStringNotContainsString( 'includePrefixedVendor()', $legacyMonolog );

		$legacyFindAssets = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php'
		);
		$this->assertStringContainsString( 'return [];', $legacyFindAssets );
		$this->assertStringNotContainsString( 'Services::WpPlugins()->getPluginsAsVo()', $legacyFindAssets );

		$legacyProcessOffense = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/Modules/IPs/Components/ProcessOffense.php'
		);
		$this->assertStringContainsString( 'public function setIP', $legacyProcessOffense );
		$this->assertStringContainsString( 'public function incrementOffenses', $legacyProcessOffense );
		$this->assertStringNotContainsString( 'new AddRule()', $legacyProcessOffense );
		$this->assertStringNotContainsString( 'IpRulesCache::Delete', $legacyProcessOffense );
		$this->assertStringNotContainsString( 'updateTransgressions', $legacyProcessOffense );

		$legacyBotSignalsRecord = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/Modules/IPs/Lib/Bots/BotSignalsRecord.php'
		);
		$this->assertStringContainsString( 'public function retrieve() :BotSignalRecord', $legacyBotSignalsRecord );
		$this->assertStringContainsString( "'notbot_at'", $legacyBotSignalsRecord );
		$this->assertStringNotContainsString( 'IpRuleStatus', $legacyBotSignalsRecord );
		$this->assertStringNotContainsString( 'IPRecords', $legacyBotSignalsRecord );
		$this->assertStringNotContainsString( 'UserMeta', $legacyBotSignalsRecord );
		$this->assertStringNotContainsString( 'LoadBotSignalRecords', $legacyBotSignalsRecord );
		$this->assertStringNotContainsString( 'Services::WpDb()', $legacyBotSignalsRecord );

		$legacyDbBotSignalRecord = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/DBs/BotSignal/BotSignalRecord.php'
		);
		$this->assertStringContainsString( 'class BotSignalRecord', $legacyDbBotSignalRecord );
		$this->assertStringContainsString( 'public function applyFromArray', $legacyDbBotSignalRecord );
		$this->assertStringNotContainsString( 'extends Ops\Record', $legacyDbBotSignalRecord );

		$runtimeDbBotSignalRecord = (string)\file_get_contents(
			$this->tempDir.'/src/DBs/BotSignal/BotSignalRecord.php'
		);
		$this->assertStringContainsString( 'extends Ops\Record', $runtimeDbBotSignalRecord );
		$this->assertStringNotContainsString( 'public function applyFromArray', $runtimeDbBotSignalRecord );

		$legacyEventDbHandler = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/DBs/Event/Ops/Handler.php'
		);
		$this->assertStringContainsString( 'public bool $use_table_ready_cache = false;', $legacyEventDbHandler );
		$this->assertStringContainsString( 'public function isReady() :bool', $legacyEventDbHandler );
		$this->assertStringContainsString( 'public function commitEvents', $legacyEventDbHandler );
		$this->assertStringContainsString( 'return false;', $legacyEventDbHandler );
		$this->assertStringNotContainsString(
			'extends \\FernleafSystems\\Wordpress\\Plugin\\Core\\Databases\\Base\\Handler',
			$legacyEventDbHandler
		);

		$legacyCrowdSecDbHandler = (string)\file_get_contents(
			$this->tempDir.'/src/lib/src/DBs/CrowdSecSignals/Ops/Handler.php'
		);
		$this->assertStringContainsString( 'public bool $use_table_ready_cache = false;', $legacyCrowdSecDbHandler );
		$this->assertStringContainsString( 'public function getRecord() :LegacyRecordStub', $legacyCrowdSecDbHandler );
		$this->assertStringContainsString( 'public function getQueryInserter() :LegacyInserterStub', $legacyCrowdSecDbHandler );
		$this->assertStringContainsString( 'public function arrayDataWrap', $legacyCrowdSecDbHandler );
		$this->assertStringContainsString( 'public function count() :int', $legacyCrowdSecDbHandler );
		$this->assertStringNotContainsString(
			'extends \\FernleafSystems\\Wordpress\\Plugin\\Core\\Databases\\Base\\Handler',
			$legacyCrowdSecDbHandler
		);
		$this->assertStringNotContainsString( 'Services::WpDb()', $legacyCrowdSecDbHandler );
	}

	public function testCreateDuplicatesSkipsPrunedLegacyPaths() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/ActivityLogs' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/ActivityLogsMeta' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/ReqLogs' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/Logging' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/vendor_prefixed/monolog' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/IpRules' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/Modules/IPs/Lib/IpRules' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/UserMeta' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/IPs' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/vendor/mlocati/ip-lib' );

		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Events/EventStrings.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/ActivityLogMessageBuilder.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/LogHandlers/LocalDbWriter.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/HackGuard/Lib/Snapshots/HashesStorageDir.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/HackGuard/Lib/Snapshots/Store.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/HackGuard/Lib/Snapshots/StoreAction/BaseAction.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/HackGuard/Lib/Snapshots/StoreAction/Load.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/Traffic/Lib/LogHandlers/LocalDbWriter.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/Event/Ops/Insert.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/Event/Ops/Record.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/CrowdSecSignals/Ops/Insert.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/CrowdSecSignals/Ops/Record.php' );

		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/Common/BaseLoadRecordsForIPJoins.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/Snapshots/Ops/Handler.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/Snapshots/Ops/Record.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/SnapshotVO.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Build.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Convert.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Diff.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Retrieve.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/LoadBotSignalRecords.php' );
		$this->assertDirectoryDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/Ops' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/ActionRouter/Exceptions/ActionException.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/ActionRouter/Actions/Traits/SecurityAdminNotRequired.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Delete.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/Snapshots/Ops/Store.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/DBs/BotSignal/BotSignalRecord.php' );
	}

	public function testCreateDuplicatesFailsWhenLegacyOverrideSourceMissing() :void {
		$this->setupMinimalPackageStructure();

		$missingOverridesRoot = $this->tempDir.'/missing-overrides';
		$duplicator = new class( $missingOverridesRoot ) extends LegacyPathDuplicator {

			private string $overridesRoot;

			public function __construct( string $overridesRoot ) {
				$this->overridesRoot = $overridesRoot;
				parent::__construct( function () {} );
			}

			protected function getLegacyOverridesRootDir() :string {
				return $this->overridesRoot;
			}
		};

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'legacy override missing' );
		$duplicator->createDuplicates( $this->tempDir );
	}

	public function testCreateDuplicatesLogsProgress() :void {
		$this->setupMinimalPackageStructure();

		$messages = [];
		$duplicator = $this->createDuplicator( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );
		$duplicator->createDuplicates( $this->tempDir );

		$this->assertTrue( \count( $messages ) > 0 );
		$hasSuccessMessage = \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'legacy path duplicates' ) !== false
		) ) > 0;
		$this->assertTrue( $hasSuccessMessage );
	}
}
