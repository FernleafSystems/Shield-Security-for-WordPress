<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	AdminPluginVisibilitySnapshot,
	CloakedPluginFinding,
	CloakedPluginState,
	CloakReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\{
	GeneratedMuLoaderContent,
	MUHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class CloakedPluginStateTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private CloakedPluginStateOptionsStub $opts;

	private const ROOT_FILE = 'vfs/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php';

	protected function setUp() :void {
		parent::setUp();
		$this->opts = new CloakedPluginStateOptionsStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'opts'      => $this->opts,
			'root_file' => self::ROOT_FILE,
			'labels'    => $this->labels(),
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRememberNewReturnsFindingOnlyOncePerFingerprint() :void {
		$state = new CloakedPluginState();
		$finding = $this->finding( 'cloaked/cloaked.php' );

		$this->assertSame( [ $finding ], $state->rememberNew( [ $finding ] ) );
		$this->assertSame( [], $state->rememberNew( [ $finding ] ) );
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testResolvedFindingsAreRemovedFromStateSoReappearingFindingsAlertAgain() :void {
		$state = new CloakedPluginState();
		$finding = $this->finding( 'cloaked/cloaked.php' );

		$state->rememberNew( [ $finding ] );
		$state->rememberNew( [] );

		$this->assertSame( [ $finding ], $state->rememberNew( [ $finding ] ) );
	}

	public function testReconcilePersistsCanonicalEvidenceAndRehydratesFromCurrentInventory() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php', 'Original Name', '1.0', '/original/path.php' );
		$finding = new CloakedPluginFinding(
			$entry,
			[ CloakReason::WpDiscoveryCacheGap, CloakReason::AllPlugins ],
			true,
			false,
			321
		);

		$initial = $state->reconcile(
			[ $finding ],
			[ $entry ],
			$this->visibility( [ $entry->file ] ),
			false
		);
		$this->assertSame( [ $finding ], $initial[ 'all' ] );
		$this->assertSame( [ $finding ], $initial[ 'new_active' ] );
		$this->assertSame(
			[ $finding->identityKey() => $this->canonicalRecord( $finding ) ],
			$this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ]
		);

		$this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ][ $finding->identityKey() ][ 'stale_name' ] = 'Never retain me';
		$currentEntry = $this->pluginEntry( $entry->file, 'Current Name', '2.0', '/current/path.php' );
		$rehydrated = $state->reconcile(
			[],
			[ $currentEntry ],
			$this->visibility( [ $entry->file ], [ $entry->file ] ),
			false
		);

		$this->assertCount( 1, $rehydrated[ 'all' ] );
		$currentFinding = $rehydrated[ 'all' ][ 0 ];
		$this->assertSame( $currentEntry, $currentFinding->entry );
		$this->assertTrue( $currentFinding->active );
		$this->assertTrue( $currentFinding->networkActive );
		$this->assertSame( $finding->cloakReasons, $currentFinding->cloakReasons );
		$this->assertSame( 321, $currentFinding->detectedAt );
		$this->assertSame(
			[ $finding->identityKey() => $this->canonicalRecord( $finding ) ],
			$this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ]
		);
	}

	public function testNonAuthoritativeEmptyRetainsFindingWithoutRenotification() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php' );
		$finding = $this->findingForEntry( $entry );

		$initial = $state->reconcile( [ $finding ], [ $entry ], $this->visibility(), false );
		$retained = $state->reconcile( [], [ $entry ], $this->visibility(), false );

		$this->assertSame( [ $finding ], $initial[ 'new_active' ] );
		$this->assertCount( 1, $retained[ 'active' ] );
		$this->assertSame( [], $retained[ 'new_active' ] );
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testAuthoritativeEmptyClearsFindingAndLaterReappearanceIsNew() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php' );
		$finding = $this->findingForEntry( $entry );

		$state->reconcile( [ $finding ], [ $entry ], $this->visibility(), false );
		$cleared = $state->reconcile( [], [ $entry ], $this->visibility(), true );

		$this->assertSame( [], $cleared[ 'all' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::OPT_KEY ] );

		$reappeared = $state->reconcile( [ $finding ], [ $entry ], $this->visibility(), false );
		$this->assertSame( [ $finding ], $reappeared[ 'new_active' ] );
	}

	public function testMissingOrNonAlertableInventoryEntryPrunesPersistedFinding() :void {
		$this->assertPersistedFindingIsPrunedWithoutCurrentAlertableEntry();
	}

	public function testCurrentPositiveReplacesStoredEvidenceWithoutDuplication() :void {
		$state = new CloakedPluginState();
		$originalEntry = $this->pluginEntry( 'cloaked/cloaked.php', 'Old', '1.0', '/old.php' );
		$original = $this->findingForEntry( $originalEntry );
		$state->reconcile( [ $original ], [ $originalEntry ], $this->visibility(), false );

		$currentEntry = $this->pluginEntry( $originalEntry->file, 'Current', '2.0', '/current.php' );
		$current = new CloakedPluginFinding(
			$currentEntry,
			[ CloakReason::PluginsList ],
			true,
			true,
			456
		);
		$result = $state->reconcile(
			[ $current ],
			[ $currentEntry ],
			$this->visibility( [ $currentEntry->file ], [ $currentEntry->file ] ),
			false
		);

		$this->assertSame( [ $current ], $result[ 'all' ] );
		$this->assertSame(
			[ $current->identityKey() => $this->canonicalRecord( $current ) ],
			$this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ]
		);
	}

	public function testSameIdentityReplacementCarriesNotificationStateAcrossEvidenceChanges() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php', 'Original', '1.0' );
		$original = $this->findingForEntry( $entry );
		$this->assertSame(
			[ $original ],
			$state->reconcile( [ $original ], [ $entry ], $this->visibility(), false )[ 'new_active' ]
		);
		$this->opts->values[ CloakedPluginState::OPT_KEY ][ $original->fingerprint() ][ 'notified_at' ] = 77;

		$currentEntry = $this->pluginEntry( $entry->file, 'Current', '2.0' );
		$metadataChanged = $this->findingForEntry( $currentEntry );
		$this->assertSame( $original->identityKey(), $metadataChanged->identityKey() );
		$this->assertNotSame( $original->fingerprint(), $metadataChanged->fingerprint() );
		$this->assertSame(
			[],
			$state->reconcile( [ $metadataChanged ], [ $currentEntry ], $this->visibility(), false )[ 'new_active' ]
		);

		$reasonChanged = $this->findingForEntry( $currentEntry, [ CloakReason::PluginsList ] );
		$this->assertNotSame( $metadataChanged->fingerprint(), $reasonChanged->fingerprint() );
		$this->assertSame(
			[],
			$state->reconcile( [ $reasonChanged ], [ $currentEntry ], $this->visibility(), false )[ 'new_active' ]
		);

		$activationChanged = new CloakedPluginFinding(
			$currentEntry,
			[ CloakReason::PluginsList ],
			true,
			true,
			123
		);
		$this->assertNotSame( $reasonChanged->fingerprint(), $activationChanged->fingerprint() );
		$this->assertSame(
			[],
			$state->reconcile(
				[ $activationChanged ],
				[ $currentEntry ],
				$this->visibility( [ $currentEntry->file ], [ $currentEntry->file ] ),
				false
			)[ 'new_active' ]
		);
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::OPT_KEY ] );
		$this->assertSame(
			$activationChanged->identityKey(),
			$this->opts->values[ CloakedPluginState::OPT_KEY ][ $activationChanged->fingerprint() ][ 'identity' ]
		);
		$this->assertSame(
			77,
			$this->opts->values[ CloakedPluginState::OPT_KEY ][ $activationChanged->fingerprint() ][ 'notified_at' ]
		);
	}

	public function testPersistedIgnoreSurvivesRetentionAndUnignoreDoesNotRealert() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php' );
		$finding = $this->findingForEntry( $entry );
		$initial = $state->reconcile( [ $finding ], [ $entry ], $this->visibility(), false );

		$this->assertTrue( $state->ignoreIdentity( $finding->identityKey(), $initial[ 'all' ] ) );
		$ignored = $state->reconcile( [], [ $entry ], $this->visibility(), false );
		$this->assertSame( [], $ignored[ 'active' ] );
		$this->assertCount( 1, $ignored[ 'ignored' ] );
		$this->assertSame( [], $ignored[ 'new_active' ] );
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] );

		$this->assertTrue( $state->unignoreIdentity( $finding->identityKey(), $ignored[ 'all' ] ) );
		$unignored = $state->reconcile( [], [ $entry ], $this->visibility(), false );
		$this->assertCount( 1, $unignored[ 'active' ] );
		$this->assertSame( [], $unignored[ 'ignored' ] );
		$this->assertSame( [], $unignored[ 'new_active' ] );
	}

	public function testPersistedGeneratedMuLoaderRetainsAutoIgnoreAndTamperActivatesIt() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$entry = new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', $path );
		$finding = $this->findingForEntry( $entry, [ CloakReason::ShowAdvancedPlugins ] );

		$initial = $state->reconcile( [ $finding ], [ $entry ], $this->visibility(), false );
		$retained = $state->reconcile( [], [ $entry ], $this->visibility(), false );
		$this->assertSame( [], $initial[ 'active' ] );
		$this->assertCount( 1, $initial[ 'ignored' ] );
		$this->assertSame( [], $retained[ 'active' ] );
		$this->assertTrue( $retained[ 'all' ][ 0 ]->active );
		$this->assertTrue( $retained[ 'all' ][ 0 ]->networkActive );

		$this->assertNotFalse( \file_put_contents(
			$path,
			( new GeneratedMuLoaderContent() )->build()."\nadd_action( 'init', 'unexpected_payload' );\n"
		) );
		$tampered = $state->reconcile( [], [ $entry ], $this->visibility(), false );
		$this->assertCount( 1, $tampered[ 'active' ] );
		$this->assertSame( [], $tampered[ 'ignored' ] );
		$this->assertCount( 1, $tampered[ 'new_active' ] );
	}

	public function testMissingOrNonArrayFindingsOptionIsTreatedAsEmpty() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php' );
		unset( $this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] );
		$this->assertSame( [], $state->reconcile( [], [ $entry ], $this->visibility(), false )[ 'all' ] );

		$this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] = 'legacy-value';
		$this->assertSame( [], $state->reconcile( [], [ $entry ], $this->visibility(), false )[ 'all' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] );
	}

	/**
	 * @dataProvider invalidPersistedFindingRecordProvider
	 */
	public function testInvalidPersistedFindingRecordIsPruned( callable $mutator, bool $includeEntry = true ) :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php' );
		$finding = $this->findingForEntry( $entry );
		list( $identity, $record ) = $mutator( $finding->identityKey(), $this->canonicalRecord( $finding ) );
		$this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] = [ $identity => $record ];

		$result = $state->reconcile( [], $includeEntry ? [ $entry ] : [], $this->visibility(), false );

		$this->assertSame( [], $result[ 'all' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] );
	}

	public static function invalidPersistedFindingRecordProvider() :array {
		return [
			'legacy notification record' => [ static fn( string $identity, array $record ) :array => [ $identity, [ 'notified_at' => 123 ] ] ],
			'scalar record' => [ static fn( string $identity, array $record ) :array => [ $identity, 'invalid' ] ],
			'missing type' => [ static function( string $identity, array $record ) :array {
				unset( $record[ 'type' ] );
				return [ $identity, $record ];
			} ],
			'invalid type' => [ static function( string $identity, array $record ) :array {
				$record[ 'type' ] = 'invalid';
				return [ $identity, $record ];
			} ],
			'non string type' => [ static function( string $identity, array $record ) :array {
				$record[ 'type' ] = 123;
				return [ $identity, $record ];
			} ],
			'empty file' => [ static function( string $identity, array $record ) :array {
				$record[ 'file' ] = '';
				return [ $identity, $record ];
			} ],
			'non string file' => [ static function( string $identity, array $record ) :array {
				$record[ 'file' ] = 123;
				return [ $identity, $record ];
			} ],
			'missing inventory entry' => [ static fn( string $identity, array $record ) :array => [ $identity, $record ], false ],
			'missing detection time' => [ static function( string $identity, array $record ) :array {
				unset( $record[ 'detected_at' ] );
				return [ $identity, $record ];
			} ],
			'non integer detection time' => [ static function( string $identity, array $record ) :array {
				$record[ 'detected_at' ] = '123';
				return [ $identity, $record ];
			} ],
			'non positive detection time' => [ static function( string $identity, array $record ) :array {
				$record[ 'detected_at' ] = 0;
				return [ $identity, $record ];
			} ],
			'empty reasons' => [ static function( string $identity, array $record ) :array {
				$record[ 'cloak_reasons' ] = [];
				return [ $identity, $record ];
			} ],
			'missing reasons' => [ static function( string $identity, array $record ) :array {
				unset( $record[ 'cloak_reasons' ] );
				return [ $identity, $record ];
			} ],
			'non list reasons' => [ static function( string $identity, array $record ) :array {
				$record[ 'cloak_reasons' ] = [ 1 => CloakReason::AllPlugins ];
				return [ $identity, $record ];
			} ],
			'invalid reason' => [ static function( string $identity, array $record ) :array {
				$record[ 'cloak_reasons' ] = [ 'invalid' ];
				return [ $identity, $record ];
			} ],
			'non string reason' => [ static function( string $identity, array $record ) :array {
				$record[ 'cloak_reasons' ] = [ 123 ];
				return [ $identity, $record ];
			} ],
			'duplicate reasons' => [ static function( string $identity, array $record ) :array {
				$record[ 'cloak_reasons' ] = [ CloakReason::AllPlugins, CloakReason::AllPlugins ];
				return [ $identity, $record ];
			} ],
			'non canonical reason order' => [ static function( string $identity, array $record ) :array {
				$record[ 'cloak_reasons' ] = [ CloakReason::AllPlugins, CloakReason::WpDiscoveryCacheGap ];
				return [ $identity, $record ];
			} ],
			'mismatched identity' => [ static fn( string $identity, array $record ) :array => [ \str_repeat( 'a', 40 ), $record ] ],
		];
	}

	public function testClassifyExcludesIgnoredFindingFromActiveAndNewActive() :void {
		$state = new CloakedPluginState();
		$finding = $this->finding( 'cloaked/cloaked.php' );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [
			$finding->identityKey(),
			'not-a-valid-identity',
		];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'ignored' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame(
			[ $finding->identityKey() ],
			$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ]
		);
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testClassifyAutoIgnoresGeneratedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$result = $state->classify( [ $finding ] );

		$this->assertTrue( $state->isAutoIgnored( $finding ) );
		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'ignored' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testClassifyPrunesStaleIgnoreForGeneratedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader() );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'ignored' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testAutoIgnoredGeneratedShieldMuLoaderCannotBeManuallyIgnoredOrUnignored() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$this->assertFalse( $state->ignoreIdentity( $finding->identityKey(), [ $finding ] ) );
		$this->assertFalse( $state->unignoreIdentity( $finding->identityKey(), [ $finding ] ) );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testUnignorePrunesStaleShieldMuLoaderIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader() );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$this->assertTrue( $state->unignoreIdentity( $finding->identityKey(), [ $finding ] ) );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyDoesNotAutoIgnoreGeneratedShieldMuLoaderWhenMuOptionIsOff() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'N';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$result = $state->classify( [ $finding ] );

		$this->assertFalse( $state->isAutoIgnored( $finding ) );
		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyActivatesShieldMuLoaderWhenMuOptionOffDespiteStaleIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'N';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader() );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyAlertsWhenPreviouslyAutoIgnoredShieldMuLoaderIsTampered() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$state->classify( [ $finding ] );
		$this->assertNotFalse( \file_put_contents(
			$path,
			( new GeneratedMuLoaderContent() )->build()."\nadd_action( 'init', 'unexpected_payload' );\n"
		) );

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
	}

	public function testClassifyActivatesTamperedShieldMuLoaderDespiteStaleIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader( "\nadd_action( 'init', 'unexpected_payload' );\n" ) );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyDoesNotAutoIgnoreTamperedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader( "\nadd_action( 'init', 'unexpected_payload' );\n" );
		$finding = $this->shieldMuFinding( $path );

		$result = $state->classify( [ $finding ] );

		$this->assertFalse( $state->isAutoIgnored( $finding ) );
		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyDoesNotAutoIgnoreShieldMuLoaderWhenUnexpected() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME );

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
	}

	public function testClassifyActivatesMissingShieldMuLoaderDespiteStaleIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$dir = $this->createTrackedTempDir( 'shield-missing-mu-' );
		$finding = $this->shieldMuFinding( $dir.'/'.MUHandler::PLUGIN_FILE_NAME );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testNormalFindingsCanStillBeIgnoredAndUnignored() :void {
		$state = new CloakedPluginState();
		$standard = $this->finding( 'cloaked/cloaked.php' );
		$mustUse = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, 'loader.php', 'Loader', '1.0', '/mu-plugins/loader.php' ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);

		$this->assertTrue( $state->ignoreIdentity( $standard->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertTrue( $state->ignoreIdentity( $mustUse->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertSame(
			[ $standard->identityKey(), $mustUse->identityKey() ],
			$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ]
		);

		$this->assertTrue( $state->unignoreIdentity( $standard->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertTrue( $state->unignoreIdentity( $mustUse->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	private function finding( string $file ) :CloakedPluginFinding {
		return $this->findingForEntry( $this->pluginEntry( $file ) );
	}

	private function findingForEntry( PluginEntry $entry, array $reasons = [ CloakReason::AllPlugins ] ) :CloakedPluginFinding {
		return new CloakedPluginFinding( $entry, $reasons, false, false, 123 );
	}

	private function pluginEntry(
		string $file,
		string $name = 'Cloaked',
		string $version = '1.0',
		?string $path = null
	) :PluginEntry {
		return new PluginEntry( PluginType::Standard, $file, $name, $version, $path ?? '/plugins/'.$file );
	}

	private function visibility( array $active = [], array $networkActive = [] ) :AdminPluginVisibilitySnapshot {
		return new AdminPluginVisibilitySnapshot( [], [], [], true, [], null, $active, $networkActive );
	}

	private function canonicalRecord( CloakedPluginFinding $finding ) :array {
		return [
			'type'          => $finding->entry->type,
			'file'          => $finding->entry->file,
			'cloak_reasons' => \array_values( $finding->cloakReasons ),
			'detected_at'   => $finding->detectedAt,
		];
	}

	private function assertPersistedFindingIsPrunedWithoutCurrentAlertableEntry() :void {
		$state = new CloakedPluginState();
		$entry = $this->pluginEntry( 'cloaked/cloaked.php' );
		$finding = $this->findingForEntry( $entry );
		$state->reconcile( [ $finding ], [ $entry ], $this->visibility(), false );

		$result = $state->reconcile( [], [], $this->visibility(), false );
		$this->assertSame( [], $result[ 'all' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::FINDINGS_OPT_KEY ] );
	}

	private function writeShieldMuLoader( string $append = '' ) :string {
		$dir = $this->createTrackedTempDir( 'shield-generated-mu-' );
		$path = $dir.'/'.MUHandler::PLUGIN_FILE_NAME;
		$this->assertNotFalse( \file_put_contents(
			$path,
			( new GeneratedMuLoaderContent() )->build().$append
		) );
		return $path;
	}

	private function shieldMuFinding( string $path ) :CloakedPluginFinding {
		return new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', $path ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);
	}

	private function labels() :object {
		return (object)[
			'Name'      => 'Shield',
			'PluginURI' => 'https://example.test/shield',
			'Author'    => 'Shield',
		];
	}
}

class CloakedPluginStateOptionsStub {

	public array $values = [
		CloakedPluginState::OPT_KEY => [],
		CloakedPluginState::IGNORE_OPT_KEY => [],
		CloakedPluginState::FINDINGS_OPT_KEY => [],
	];

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? [];
	}

	public function optSet( string $key, $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}

	public function optIs( string $key, $value ) :bool {
		return ( $this->values[ $key ] ?? null ) === $value;
	}

	public function store() :self {
		return $this;
	}
}
