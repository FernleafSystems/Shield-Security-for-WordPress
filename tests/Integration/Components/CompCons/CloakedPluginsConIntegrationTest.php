<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\CloakedPluginState;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPluginsCon;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerCloakedPlugins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Processor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\CloakedPluginFixtureTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class CloakedPluginsConIntegrationTest extends ShieldIntegrationTestCase {

	use CloakedPluginFixtureTrait;
	use LocalEmailCapture;

	private array $optionsSnapshot = [];
	private ?string $originalPageNow = null;
	private array $originalGet = [];
	private array $originalQuery = [];

	public function set_up() {
		parent::set_up();
		global $pagenow;
		$this->originalPageNow = $pagenow ?? null;
		$this->originalGet = $_GET;
		unset( $_GET[ 'plugin_status' ], $_GET[ 's' ] );
		$this->originalQuery = Services::Request()->query;
		Services::Request()->query = $_GET;
		$this->startLocalEmailCapture();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'instant_alert_hidden_plugins',
			'instant_alerts_data',
			CloakedPluginState::OPT_KEY,
			CloakedPluginState::IGNORE_OPT_KEY,
			CloakedPluginState::FINDINGS_OPT_KEY,
			'block_send_email_address',
		] );
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'email' )
			->optSet( 'instant_alerts_data', [] )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->optSet( CloakedPluginState::IGNORE_OPT_KEY, [] )
			->optSet( CloakedPluginState::FINDINGS_OPT_KEY, [] )
			->optSet( 'block_send_email_address', 'cloaked-plugins@example.com' )
			->store();
		$this->resetInstantAlertHandlers();
	}

	public function tear_down() {
		$_GET = $this->originalGet;
		Services::Request()->query = $this->originalQuery;
		$this->removeCloakedPluginFixtureFilters();
		$this->cleanupCloakedPluginFixtures();
		$this->resetCloakedPluginFindingsCache();
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->resetInstantAlertHandlers();
		}
		if ( $this->originalPageNow === null ) {
			unset( $GLOBALS[ 'pagenow' ] );
		}
		else {
			$GLOBALS[ 'pagenow' ] = $this->originalPageNow;
		}
		parent::tear_down();
	}

	public function testPageScopedFindingPersistsAcrossRequestsWithoutDuplicateSideEffects() :void {
		global $pagenow;

		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-persisted', 'SHI Cloaked Persisted' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		$this->captureShieldEvents();

		$pagenow = 'plugins.php';
		$this->assertCount( 1, $this->observeRealPluginsTable() );
		$this->assertCloakedPluginEvent( $pluginFile, 'all_plugins' );
		$this->assertCount( 1, $this->capturedMails() );

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->detect() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function testAuthoritativeNormalPluginsListClearsPersistedFinding() :void {
		global $pagenow;

		$this->createStandardCloakedPlugin( 'shi-cloaked-resolved', 'SHI Cloaked Resolved' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );

		$pagenow = 'plugins.php';
		$this->assertCount( 1, $this->observeRealPluginsTable() );

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		$pagenow = 'plugins.php';
		$this->assertSame( [], $this->observeRealPluginsTable() );

		$this->resetCloakedPluginFindingsCache();
		$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect() );
	}

	public function testResolvedFindingReappearsWithOneNewEventAndEmail() :void {
		global $pagenow;

		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-reappears', 'SHI Cloaked Reappears' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		$this->captureShieldEvents();

		$pagenow = 'plugins.php';
		$this->assertCount( 1, $this->observeRealPluginsTable() );

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		$pagenow = 'plugins.php';
		$this->assertSame( [], $this->observeRealPluginsTable() );

		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		$pagenow = 'plugins.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 1, $this->observeRealPluginsTable() );
		$this->assertCount( 2, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 2, $this->capturedMails() );
		$this->assertSame( $pluginFile, $this->lastCloakedPluginEventFile() );
	}

	public function testDeletedPluginClearsPersistedFindingWithoutAuthoritativeList() :void {
		global $pagenow;

		$this->createStandardCloakedPlugin( 'shi-cloaked-deleted', 'SHI Cloaked Deleted' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );

		$pagenow = 'plugins.php';
		$this->assertCount( 1, $this->observeRealPluginsTable() );
		$this->removeCloakedPluginFixtures();

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect() );
	}

	public function testStandardPluginCloakedByAllPluginsFilterFiresEventAndDedupeAlert() :void {
		global $pagenow;
		$pagenow = 'plugins.php';

		$con = $this->requireController();
		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-all', 'SHI Cloaked All Plugins' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );

		$this->captureShieldEvents();

		$this->observeRealPluginsTable();
		$this->assertCloakedPluginEvent( $pluginFile, 'all_plugins' );
		$this->assertCount( 1, $this->capturedMails() );
		$mail = $this->lastCapturedMail();
		$this->assertArrayHasKey( 'html_body', $mail );
		$this->assertHtmlContainsMarker(
			$pluginFile,
			(string)$mail[ 'html_body' ],
			'Cloaked plugin alert HTML body'
		);

		$this->observeRealPluginsTable();
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function testBackgroundCheckFollowedByAdminObservationDetectsCloaking() :void {
		global $pagenow;
		$pagenow = 'admin.php';

		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-background', 'SHI Cloaked Background' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();

		$this->requireController()->comps->hidden_plugins->detect();
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->resetCloakedPluginFindingsCache();
		$this->prepareRealPluginsTable();
		$this->assertCloakedPluginEvent( $file, 'all_plugins' );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function testBackgroundCheckFollowedByAdminObservationDetectsFinalListCloaking() :void {
		global $pagenow;
		$pagenow = 'admin.php';

		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-background-list', 'SHI Cloaked Background List' );
		add_filter( 'plugins_list', [ $this, 'hideCloakedPluginFromPluginsList' ], 1000 );
		$this->captureShieldEvents();

		$this->requireController()->comps->hidden_plugins->detect();
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->resetCloakedPluginFindingsCache();
		$this->prepareRealPluginsTable();
		$this->assertCloakedPluginEvent( $file, 'plugins_list' );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function testBackgroundPluginsListObservationIsIgnored() :void {
		global $pagenow;
		$pagenow = 'admin.php';

		$this->createStandardCloakedPlugin( 'shi-cloaked-background-observer', 'SHI Cloaked Background Observer' );
		$plugins = [ 'all' => [], 'mustuse' => [] ];
		$this->captureShieldEvents();

		$this->assertSame( $plugins, $this->requireController()->comps->hidden_plugins->observePluginsList( $plugins ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertSame( [], $this->capturedMails() );
	}

	/** @dataProvider emptyStandardListStages */
	public function testEmptyStandardListDoesNotAccusePlugins( string $stage ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->createStandardCloakedPlugin( 'shi-cloaked-empty-a', 'Empty A' );
		$this->createStandardCloakedPlugin( 'shi-cloaked-empty-b', 'Empty B' );
		$this->captureShieldEvents();
		$detector = $this->requireController()->comps->hidden_plugins;
		$this->assertCount( 0, $this->observeRealPluginsTable() );

		$restore = $this->injectEmptyStandardList( $stage );
		try {
			for ( $observation = 0; $observation < 2; $observation++ ) {
				$this->resetCloakedPluginFindingsCache();
				$findings = $stage === 'discovery_cache' ? $detector->detect() : $this->observeRealPluginsTable();
				$this->assertCount( 0, $findings, 'An unusable list is not evidence against individual plugins.' );
				$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
				$this->assertCount( 0, $this->capturedMails() );
			}
		}
		finally {
			$restore();
		}
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 0, $this->observeRealPluginsTable() );
		$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
	}

	public static function emptyStandardListStages() :array {
		return [ 'all_plugins' => [ 'all_plugins' ], 'plugins_list' => [ 'plugins_list' ], 'discovery_cache' => [ 'discovery_cache' ] ];
	}

	public function testMalformedDiscoveryDoesNotAccusePlugins() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->createStandardCloakedPlugin( 'shi-cloaked-malformed-visible', 'Visible plugin' );
		$this->captureShieldEvents();
		$this->assertCount( 0, $this->observeRealPluginsTable() );
		$restore = $this->injectMalformedDiscovery();
		try {
			$this->assertFalse( get_plugins() );
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				$this->resetCloakedPluginFindingsCache();
				$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect() );
				$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
				$this->assertCount( 0, $this->capturedMails() );
			}
		}
		finally {
			$restore();
		}
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 0, $this->observeRealPluginsTable() );
		$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 0, $this->capturedMails() );
	}

	/** @dataProvider malformedDiscoveryIgnoreCases */
	public function testMalformedDiscoveryPreservesSavedFindings( bool $ignored ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$muFile = $this->createMustUseCloakedPlugin( 'shi-cloaked-malformed-mu.php', 'Saved MU' );
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-malformed-saved', 'Saved standard' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000, 2 );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		$findings = $this->observeRealPluginsTable();
		$this->assertCount( 2, $findings );
		if ( $ignored ) {
			foreach ( $findings as $finding ) {
				if ( $finding->entry->file === $file ) {
					$this->assertTrue( ( new CloakedPluginState() )->ignoreIdentity( $finding->identityKey(), $findings ) );
				}
			}
			$this->resetCloakedPluginFindingsCache();
			$this->observeRealPluginsTable();
		}
		$initial = $this->captureCloakedObservation();
		$this->assertEqualsCanonicalizing( [ $file, $muFile ], $initial[ 'all' ] );
		$this->assertSame( $ignored ? [ $file ] : [], $initial[ 'ignored' ] );
		$con = $this->requireController();
		$saved = $this->snapshotSelectedOptions( [ CloakedPluginState::FINDINGS_OPT_KEY, CloakedPluginState::IGNORE_OPT_KEY ] );
		$notified = $con->opts->optGet( CloakedPluginState::OPT_KEY );
		$restore = $this->injectMalformedDiscovery();
		try {
			$this->assertFalse( get_plugins() );
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				$this->resetCloakedPluginFindingsCache();
				if ( $repeat === 0 ) {
					$con->comps->hidden_plugins->detect();
				}
				$this->assertSame( $initial, $this->captureCloakedObservation() );
				$this->assertSame( $saved, $this->snapshotSelectedOptions( \array_keys( $saved ) ) );
				$current = $con->opts->optGet( CloakedPluginState::OPT_KEY );
				$this->assertSame( \array_keys( $notified ), \array_keys( $current ) );
				foreach ( $notified as $key => $record ) {
					$this->assertSame( $record[ 'identity' ], $current[ $key ][ 'identity' ] );
					$this->assertSame( $record[ 'notified_at' ], $current[ $key ][ 'notified_at' ] );
				}
			}
		}
		finally {
			$restore();
		}
		$this->resetCloakedPluginFindingsCache();
		$this->observeRealPluginsTable();
		$this->assertSame( $initial, $this->captureCloakedObservation() );
		remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		remove_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000 );
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 0, $this->observeRealPluginsTable() );
		$this->assertSame( $initial[ 'events' ], \count( $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) ) );
		$this->assertSame( $initial[ 'mails' ], \count( $this->capturedMails() ) );
	}

	public static function malformedDiscoveryIgnoreCases() :array {
		return [ 'active' => [ false ], 'ignored' => [ true ] ];
	}

	public function testMalformedDiscoveryReadDoesNotBreakPageObservation() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createMustUseCloakedPlugin( 'shi-cloaked-malformed-page.php', 'Page MU' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000, 2 );
		$this->captureShieldEvents();
		$restore = null;
		$damageCache = function ( array $plugins ) use ( &$restore ) :array {
			$restore = $this->injectMalformedDiscovery();
			$this->assertFalse( get_plugins() );
			return $plugins;
		};
		add_filter( 'all_plugins', $damageCache, PHP_INT_MIN );
		try {
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				try {
					$this->resetCloakedPluginFindingsCache();
					$findings = $this->observeRealPluginsTable();
					$this->assertCount( 1, $findings );
					$this->assertSame( $file, $findings[ 0 ]->entry->file );
					$this->assertCloakedPluginEvent( $file, 'show_advanced_plugins' );
					$this->assertCount( 1, $this->capturedMails() );
				}
				finally {
					if ( $restore !== null ) {
						$restore();
						$restore = null;
					}
				}
			}
		}
		finally {
			remove_filter( 'all_plugins', $damageCache, PHP_INT_MIN );
		}
		remove_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000 );
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 0, $this->observeRealPluginsTable() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	private function injectMalformedDiscovery() :\Closure {
		$cached = wp_cache_get( 'plugins', 'plugins', false, $found );
		wp_cache_set( 'plugins', [ '' => false ], 'plugins' );
		return static function () use ( $cached, $found ) :void {
			$found ? wp_cache_set( 'plugins', $cached, 'plugins' ) : wp_cache_delete( 'plugins', 'plugins' );
		};
	}

	public function testRealPluginsTableSurvivesEmptyDiscoveryAndRecovery() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-table-cache', 'Table cache control' );
		$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable() );
		$restore = $this->injectEmptyStandardList( 'discovery_cache' );
		try {
			$this->resetCloakedPluginFindingsCache();
			$items = $this->prepareRealPluginsTable();
			$this->assertArrayNotHasKey( $file, $items, 'The controlled empty discovery must reach the actual table.' );
		}
		finally {
			$restore();
		}
		$this->resetCloakedPluginFindingsCache();
		$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable() );
	}

	/** @dataProvider muEvidenceChecks */
	public function testEmptyStandardListPreservesIndependentMustUseEvidence( string $check, string $stage ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createMustUseCloakedPlugin( 'shi-cloaked-independent.php', 'Independent MU' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000, 2 );
		$detector = $this->requireController()->comps->hidden_plugins;
		$this->captureShieldEvents();
		$findings = $this->observeRealPluginsTable();
		$this->assertCount( 1, $findings );
		$this->assertSame( $file, $findings[ 0 ]->entry->file );
		if ( $check === 'ignore' ) {
			$this->assertTrue( ( new CloakedPluginState() )->ignoreIdentity( $findings[ 0 ]->identityKey(), $findings ) );
			$this->resetCloakedPluginFindingsCache();
			$this->observeRealPluginsTable();
		}
		$initial = $this->captureCloakedObservation();
		$observations = [];
		$restore = $this->injectEmptyStandardList( $stage );
		try {
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				$this->resetCloakedPluginFindingsCache();
				// State coverage is independent of the real-table cache test.
				$stage === 'discovery_cache' ? $detector->detect() : $this->observeRealPluginsTable();
				$observations[ 'empty '.$repeat ] = $this->captureCloakedObservation();
			}
		}
		finally {
			$restore();
		}
		$this->resetCloakedPluginFindingsCache();
		$this->observeRealPluginsTable();
		$observations[ 'recovered' ] = $this->captureCloakedObservation();

		foreach ( $observations as $phase => $observation ) {
			if ( $check === 'evidence' ) {
				$this->assertCount( 1, $observation[ 'all' ], $phase.': retain only the genuine finding.' );
				$this->assertContains( $file, $observation[ 'active' ], $phase.': retain independent MU evidence.' );
			}
			elseif ( $check === 'ignore' ) {
				$this->assertCount( 1, $observation[ 'all' ], $phase.': do not manufacture findings.' );
				$this->assertSame( [ $file ], $observation[ 'ignored' ], $phase.': retain the ignore choice.' );
				$this->assertCount( 0, $observation[ 'active' ], $phase.': the ignored finding must stay ignored.' );
			}
			else {
				$this->assertSame( $initial[ 'events' ], $observation[ 'events' ], $phase.': no new cloaking event.' );
				$this->assertSame( $initial[ 'mails' ], $observation[ 'mails' ], $phase.': no duplicate email.' );
			}
		}
	}

	public static function muEvidenceChecks() :array {
		$cases = [];
		foreach ( [ 'all_plugins', 'plugins_list', 'discovery_cache' ] as $stage ) {
			foreach ( [ 'evidence', 'ignore', 'dedupe' ] as $check ) {
				$cases[ $stage.' '.$check ] = [ $check, $stage ];
			}
		}
		return $cases;
	}

	/** @dataProvider savedStandardFindingCases */
	public function testEmptyListPreservesStandardFinding( string $stage, bool $ignored ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-saved', 'Saved finding' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		$findings = $this->observeRealPluginsTable();
		$this->assertCount( 1, $findings );
		$this->assertSame( $file, $findings[ 0 ]->entry->file );
		if ( $ignored ) {
			$this->assertTrue( ( new CloakedPluginState() )->ignoreIdentity( $findings[ 0 ]->identityKey(), $findings ) );
			$this->resetCloakedPluginFindingsCache();
			$this->observeRealPluginsTable();
		}
		$initial = $this->captureCloakedObservation();
		$observations = [];
		$restore = $this->injectEmptyStandardList( $stage );
		try {
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				$this->resetCloakedPluginFindingsCache();
				$this->observeRealPluginsTable();
				$observations[ 'empty '.$repeat ] = $this->captureCloakedObservation();
			}
		}
		finally {
			$restore();
		}
		$this->resetCloakedPluginFindingsCache();
		$this->observeRealPluginsTable();
		$observations[ 'recovered' ] = $this->captureCloakedObservation();

		foreach ( $observations as $phase => $observation ) {
			$this->assertCount( 1, $observation[ 'all' ], $phase.': retain only the genuine finding.' );
			$this->assertSame( [ $file ], $observation[ $ignored ? 'ignored' : 'active' ], $phase.': retain the finding and ignore status.' );
			$this->assertCount( 0, $observation[ $ignored ? 'active' : 'ignored' ], $phase.': do not change ignore status.' );
			$this->assertSame( $initial[ 'events' ], $observation[ 'events' ], $phase.': no new cloaking event.' );
			$this->assertSame( $initial[ 'mails' ], $observation[ 'mails' ], $phase.': no duplicate email.' );
		}
	}

	public static function savedStandardFindingCases() :array {
		return [
			'all_plugins active' => [ 'all_plugins', false ],
			'all_plugins ignored' => [ 'all_plugins', true ],
			'plugins_list active' => [ 'plugins_list', false ],
			'plugins_list ignored' => [ 'plugins_list', true ],
		];
	}

	/** Snapshot public results before the next simulated request changes them. */
	private function captureCloakedObservation() :array {
		$state = $this->requireController()->comps->hidden_plugins->currentState();
		$result = [];
		foreach ( [ 'all', 'active', 'ignored' ] as $key ) {
			$result[ $key ] = \array_map( static fn( $finding ) => $finding->entry->file, $state[ $key ] );
		}
		$result[ 'events' ] = \count( $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$result[ 'mails' ] = \count( $this->capturedMails() );
		return $result;
	}

	/** @dataProvider incompleteObservations */
	public function testIncompleteObservationPreservesSavedEvidence( $list ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-incomplete', 'Incomplete' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		$this->assertCount( 1, $this->observeRealPluginsTable() );
		remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->resetCloakedPluginFindingsCache();
		$detector = $this->requireController()->comps->hidden_plugins;
		apply_filters( 'all_plugins', get_plugins() );
		$this->assertSame( $list, $detector->observePluginsList( $list ) );
		$GLOBALS[ 'pagenow' ] = 'admin.php';
		$this->assertCount( 1, $detector->currentFindings() );
		$this->assertSame( $file, $detector->currentFindings()[ 0 ]->entry->file );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
	}

	public static function incompleteObservations() :array {
		return [ 'null' => [ null ], 'false' => [ false ], 'missing standard bucket' => [ [ 'mustuse' => [] ] ], 'invalid standard bucket' => [ [ 'all' => null, 'mustuse' => [] ] ] ];
	}

	public function testShieldAloneMissingIsDetectedWithoutBlamingOtherPlugins() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->createStandardCloakedPlugin( 'shi-cloaked-visible', 'Visible control' );
		$file = $this->requireController()->base_file;
		$hideShield = static function ( array $plugins ) use ( $file ) :array {
			unset( $plugins[ $file ] );
			return $plugins;
		};
		add_filter( 'all_plugins', $hideShield, 999 );
		$this->captureShieldEvents();
		try {
			$findings = $this->observeRealPluginsTable();
			$this->assertCount( 1, $findings );
			$this->assertSame( $file, $findings[ 0 ]->entry->file );
			$this->assertCloakedPluginEvent( $file, 'all_plugins' );
		}
		finally {
			remove_filter( 'all_plugins', $hideShield, 999 );
		}
	}

	public function testObservedVisiblePluginIsNotAccusedByASecondStatefulFilterInvocation() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-stateful', 'Stateful' );
		$calls = 0;
		$filter = static function ( array $plugins ) use ( &$calls, $file ) :array {
			if ( ++$calls > 1 ) {
				unset( $plugins[ $file ] );
			}
			return $plugins;
		};
		add_filter( 'all_plugins', $filter, 1000 );
		$this->captureShieldEvents();
		try {
			$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable() );
			$this->assertSame( 1, $calls, 'Shield must not replay the filter.' );
			$this->assertCount( 0, $this->requireController()->comps->hidden_plugins->currentFindings(), 'A second filter result must not contradict the observed visible list.' );
			$this->assertSame( [], $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
			$this->assertSame( [], $this->capturedMails() );
		}
		finally {
			remove_filter( 'all_plugins', $filter, 1000 );
		}
	}

	public function testBackgroundOnlyEmptyFilterDoesNotAccuseVisiblePlugins() :void {
		$GLOBALS[ 'pagenow' ] = 'admin.php';
		$this->createStandardCloakedPlugin( 'shi-cloaked-context', 'Context' );
		$filter = static fn( array $plugins ) :array => $GLOBALS[ 'pagenow' ] === 'plugins.php' ? $plugins : [];
		add_filter( 'all_plugins', $filter, 1000 );
		$this->captureShieldEvents();
		try {
			$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect() );
			$GLOBALS[ 'pagenow' ] = 'plugins.php';
			$this->resetCloakedPluginFindingsCache();
			$this->assertSame( [], $this->observeRealPluginsTable() );
			$this->assertSame( [], $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
			$this->assertSame( [], $this->capturedMails() );
		}
		finally {
			remove_filter( 'all_plugins', $filter, 1000 );
		}
	}

	public function testBackgroundCheckFollowedByAdminObservationDetectsMustUseCloaking() :void {
		unset( $GLOBALS[ 'pagenow' ] );
		$file = $this->createMustUseCloakedPlugin( 'shi-cloaked-background-mu.php', 'Background MU' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000, 2 );
		$this->captureShieldEvents();
		$this->requireController()->comps->hidden_plugins->triggerDetection();
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->resetCloakedPluginFindingsCache();
		$this->prepareRealPluginsTable();
		$this->assertCloakedPluginEvent( $file, 'show_advanced_plugins' );
	}

	public function testBackgroundOnlySinglePluginFilterDoesNotAccuseAVisiblePlugin() :void {
		$GLOBALS[ 'pagenow' ] = 'admin.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-context-single', 'Context single' );
		$filter = static function ( array $plugins ) use ( $file ) :array {
			if ( $GLOBALS[ 'pagenow' ] !== 'plugins.php' ) {
				unset( $plugins[ $file ] );
			}
			return $plugins;
		};
		add_filter( 'all_plugins', $filter, 1000 );
		$this->captureShieldEvents();
		try {
			for ( $cycle = 0; $cycle < 3; $cycle++ ) {
				$GLOBALS[ 'pagenow' ] = 'admin.php';
				$this->resetCloakedPluginFindingsCache();
				$this->requireController()->comps->hidden_plugins->detect();
				$GLOBALS[ 'pagenow' ] = 'plugins.php';
				$this->resetCloakedPluginFindingsCache();
				$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable() );
				$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ), 'Misleading background results must not generate alerts across repeated cycles.' );
				$this->assertCount( 0, $this->capturedMails() );
			}
		}
		finally {
			remove_filter( 'all_plugins', $filter, 1000 );
		}
	}

	public function testConfirmedCloakingAlertsAgainOnlyAfterConfirmedRecovery() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-confirmed-cycle', 'Confirmed cycle' );
		$this->captureShieldEvents();
		$filter = [ $this, 'hideCloakedPluginFromAllPlugins' ];
		for ( $cycle = 1; $cycle <= 2; $cycle++ ) {
			add_filter( 'all_plugins', $filter, 1000 );
			try {
				for ( $observation = 0; $observation < 2; $observation++ ) {
					$this->resetCloakedPluginFindingsCache();
					$this->assertArrayNotHasKey( $file, $this->prepareRealPluginsTable() );
					$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->currentFindings() );
					$this->assertCount( $cycle, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
					$this->assertCount( $cycle, $this->capturedMails() );
				}
			}
			finally {
				remove_filter( 'all_plugins', $filter, 1000 );
			}
			$this->resetCloakedPluginFindingsCache();
			$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable() );
			$this->assertCount( 0, $this->requireController()->comps->hidden_plugins->currentFindings() );
			$this->assertCount( $cycle, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
			$this->assertCount( $cycle, $this->capturedMails() );
		}
	}

	public function testBackgroundEmptyDiscoveryDoesNotAccusePlugins() :void {
		unset( $GLOBALS[ 'pagenow' ] );
		$this->createStandardCloakedPlugin( 'shi-cloaked-empty-background', 'Empty background' );
		$this->captureShieldEvents();
		$restore = $this->injectEmptyStandardList( 'discovery_cache' );
		try {
			$detector = $this->requireController()->comps->hidden_plugins;
			for ( $observation = 0; $observation < 2; $observation++ ) {
				$this->resetCloakedPluginFindingsCache();
				$findings = $detector->detect();
				$this->assertCount( 0, $findings );
				$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
			}
		}
		finally {
			$restore();
		}
	}

	private function observeRealPluginsTable() :array {
		$this->prepareRealPluginsTable();
		return $this->requireController()->comps->hidden_plugins->currentFindings();
	}

	/** @dataProvider backgroundEntryPoints */
	public function testBackgroundEntryPointFollowedByAdminObservationDetectsCloaking( string $entryPoint ) :void {
		unset( $GLOBALS[ 'pagenow' ] );
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-entry', 'Entry point' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		if ( $entryPoint === 'hourly' ) {
			( new Processor() )->runHourlyCron();
		}
		else {
			$this->assertNull( activate_plugin( $file ) );
			$this->assertTrue( is_plugin_active( $file ) );
		}
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->resetCloakedPluginFindingsCache();
		$this->prepareRealPluginsTable();
		$this->assertCloakedPluginEvent( $file, 'all_plugins' );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public static function backgroundEntryPoints() :array {
		return [ 'hourly' => [ 'hourly' ], 'activation' => [ 'activation' ] ];
	}

	/** @dataProvider realTableFilters */
	public function testRealWordPressTableAndAlertsAgree( string $hook, bool $stateful, bool $hide ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-table', 'Table fixture' );
		$calls = 0;
		$filter = static function ( array $value ) use ( &$calls, $file, $hook, $stateful, $hide ) :array {
			$calls++;
			if ( $hide && ( !$stateful || $calls > 1 ) ) {
				if ( $hook === 'all_plugins' ) {
					unset( $value[ $file ] );
				}
				else {
					foreach ( $value as &$group ) {
						if ( \is_array( $group ) ) {
							unset( $group[ $file ] );
						}
					}
					unset( $group );
				}
			}
			return $value;
		};
		// Before Shield's page-view hook, as third-party filters commonly run.
		add_filter( $hook, $filter, 999 );
		$this->captureShieldEvents();
		try {
			$items = $this->prepareRealPluginsTable();
			if ( $hide && !$stateful ) {
				$this->assertArrayNotHasKey( $file, $items );
				$this->assertCloakedPluginEvent( $file, $hook );
				$this->assertCount( 1, $this->capturedMails() );
			}
			else {
				$this->assertArrayHasKey( $file, $items, 'WordPress actually displays this plugin.' );
				$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ), 'A visible plugin must not generate a cloaking event during table construction.' );
				$this->assertCount( 0, $this->capturedMails() );
				$this->assertCount( 0, $this->requireController()->comps->hidden_plugins->currentFindings() );
			}
		}
		finally {
			remove_filter( $hook, $filter, 999 );
		}
	}

	public static function realTableFilters() :array {
		return [
			'visible control' => [ 'all_plugins', false, false ],
			'all_plugins hidden control' => [ 'all_plugins', false, true ],
			'plugins_list hidden control' => [ 'plugins_list', false, true ],
			'all_plugins changes on replay' => [ 'all_plugins', true, true ],
			'plugins_list changes on replay' => [ 'plugins_list', true, true ],
		];
	}

	/** @dataProvider transientWholeListHooks */
	public function testTransientWholeListReplayDoesNotAccuseVisiblePluginsAcrossRequests( string $hook ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$files = [
			$this->createStandardCloakedPlugin( 'shi-cloaked-transient-a', 'Transient A' ),
			$this->createStandardCloakedPlugin( 'shi-cloaked-transient-b', 'Transient B' ),
		];
		$calls = 0;
		$firstRequest = true;
		$filter = static function ( array $value ) use ( &$calls, &$firstRequest, $hook ) :array {
			if ( !$firstRequest || ++$calls === 1 ) {
				return $value;
			}
			if ( $hook === 'all_plugins' ) {
				return [];
			}
			foreach ( $value as $group => $rows ) {
				if ( !\in_array( $group, [ 'mustuse', 'dropins', 'cloaked' ], true ) ) {
					$value[ $group ] = [];
				}
			}
			return $value;
		};
		add_filter( $hook, $filter, 999 );
		$this->captureShieldEvents();
		try {
			$firstItems = $this->prepareRealPluginsTable();
			$observations[ 'initial table' ] = $this->captureCloakedObservation();
			$firstRequest = false;

			$GLOBALS[ 'pagenow' ] = 'admin.php';
			$this->resetCloakedPluginFindingsCache();
			$this->requireController()->comps->hidden_plugins->detect();
			$observations[ 'later admin' ] = $this->captureCloakedObservation();

			$GLOBALS[ 'pagenow' ] = 'plugins.php';
			$this->resetCloakedPluginFindingsCache();
			$recoveredItems = $this->prepareRealPluginsTable();
			$observations[ 'normal table' ] = $this->captureCloakedObservation();

			$summary = [];
			foreach ( $observations as $phase => $observation ) {
				$summary[ $phase ] = [
					'findings' => \count( $observation[ 'all' ] ),
					'events' => $observation[ 'events' ],
					'mails' => $observation[ 'mails' ],
				];
			}
			$trace = \json_encode( $summary );
			foreach ( $files as $file ) {
				$this->assertArrayHasKey( $file, $firstItems, 'The first actual table must contain both controls.' );
				$this->assertArrayHasKey( $file, $recoveredItems, 'The later actual table must contain both controls.' );
			}
			foreach ( $observations as $phase => $observation ) {
				$this->assertCount( 0, $observation[ 'all' ], $phase.': false finding trajectory '.$trace );
				$this->assertSame( 0, $observation[ 'events' ], $phase.': false event trajectory '.$trace );
				$this->assertSame( 0, $observation[ 'mails' ], $phase.': false email trajectory '.$trace );
			}
		}
		finally {
			remove_filter( $hook, $filter, 999 );
		}
	}

	public static function transientWholeListHooks() :array {
		return [ 'all_plugins' => [ 'all_plugins' ], 'plugins_list' => [ 'plugins_list' ] ];
	}

	/** @dataProvider restrictedListContexts */
	public function testRestrictedListCannotClearAnExistingFinding( array $query ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-restricted', 'Restricted' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$detector = $this->requireController()->comps->hidden_plugins;
		$this->captureShieldEvents();
		$this->assertCount( 1, $this->observeRealPluginsTable() );
		remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$_GET = $query;
		Services::Request()->query = $query;
		$this->resetCloakedPluginFindingsCache();
		$this->prepareRealPluginsTable();
		$this->assertCount( 1, $detector->currentFindings(), 'A restricted list must not manufacture additional findings.' );
		$this->assertSame( $file, $detector->currentFindings()[ 0 ]->entry->file );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public static function restrictedListContexts() :array {
		return [ 'search' => [ [ 's' => 'no matches' ] ], 'inactive' => [ [ 'plugin_status' => 'inactive' ] ], 'cloaked' => [ [ 'plugin_status' => 'cloaked' ] ] ];
	}

	public function testPluginsActionRequestDoesNotTurnAnActionOnlyFilterIntoAnAlert() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$_GET[ 'action' ] = 'activate';
		Services::Request()->query = $_GET;
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-action', 'Action' );
		$filter = static function ( array $plugins ) use ( $file ) :array {
			if ( ( $_GET[ 'action' ] ?? '' ) === 'activate' ) {
				unset( $plugins[ $file ] );
			}
			return $plugins;
		};
		add_filter( 'all_plugins', $filter, 1000 );
		$this->captureShieldEvents();
		try {
			$this->requireController()->comps->hidden_plugins->triggerDetection();
			unset( $_GET[ 'action' ] );
			Services::Request()->query = $_GET;
			$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable() );
			$this->assertCount( 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ), 'An action-only filter result is not proof of cloaking in the displayed list.' );
			$this->assertCount( 0, $this->capturedMails() );
		}
		finally {
			remove_filter( 'all_plugins', $filter, 1000 );
		}
	}


	private function prepareRealPluginsTable( bool $network = false ) :array {
		$keys = [ 'current_screen', 'status', 'plugins', 'totals', 'page', 'orderby', 'order', 's' ];
		$globals = [];
		foreach ( $keys as $key ) {
			if ( \array_key_exists( $key, $GLOBALS ) ) {
				$globals[ $key ] = $GLOBALS[ $key ];
			}
		}
		$request = $_REQUEST;
		$user = get_current_user_id();
		try {
			$_REQUEST = [];
			$GLOBALS[ 's' ] = '';
			wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
			update_user_option( get_current_user_id(), 'plugins_per_page', 999 );
			set_current_screen( $network ? 'plugins-network' : 'plugins' );
			require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
			require_once ABSPATH.'wp-admin/includes/class-wp-plugins-list-table.php';
			$table = new \WP_Plugins_List_Table( [ 'screen' => get_current_screen() ] );
			$table->prepare_items();
			return $table->items;
		}
		finally {
			$_REQUEST = $request;
			wp_set_current_user( $user );
			foreach ( $keys as $key ) {
				if ( \array_key_exists( $key, $globals ) ) {
					$GLOBALS[ $key ] = $globals[ $key ];
				}
				else {
					unset( $GLOBALS[ $key ] );
				}
			}
		}
	}

	private function injectEmptyStandardList( string $stage ) :\Closure {
		if ( $stage === 'discovery_cache' ) {
			$cached = wp_cache_get( 'plugins', 'plugins', false, $found );
			wp_cache_set( 'plugins', [ '' => [] ], 'plugins' );
			return static function () use ( $cached, $found ) :void {
				$found ? wp_cache_set( 'plugins', $cached, 'plugins' ) : wp_cache_delete( 'plugins', 'plugins' );
			};
		}
		$filter = $stage === 'all_plugins' ? static fn( array $plugins ) :array => []
			: static function ( array $groups ) :array {
				foreach ( $groups as $key => $group ) {
					if ( $key !== 'mustuse' && $key !== 'dropins' && $key !== 'cloaked' ) {
						$groups[ $key ] = [];
					}
				}
				return $groups;
			};
		add_filter( $stage, $filter, 1000 );
		return static function () use ( $stage, $filter ) :void {
			remove_filter( $stage, $filter, 1000 );
		};
	}

	/** @dataProvider supportedWordPressVersions */
	public function testWordPressVersionGatesDetection( string $version, bool $available ) :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->createStandardCloakedPlugin( 'shi-cloaked-version', 'Version boundary' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		$this->withWordPressVersion( $version, function () use ( $available ) :void {
			$this->assertSame( $available, $this->requireController()->caps->canDetectCloakedPlugins() );
			$this->assertCount( $available ? 1 : 0, $this->observeRealPluginsTable() );
			$this->assertCount( $available ? 1 : 0, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
			$this->assertCount( $available ? 1 : 0, $this->capturedMails() );
		} );
	}

	public static function supportedWordPressVersions() :array {
		return [ 'older' => [ '6.2.9', false ], 'minimum' => [ '6.3', true ], 'newer' => [ '6.9', true ] ];
	}

	public function testUnsupportedVersionPreservesSavedStateAndDoesNotSendQueuedAlert() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$this->createStandardCloakedPlugin( 'shi-cloaked-old-wp', 'Saved result' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		$findings = $this->observeRealPluginsTable();
		$this->assertCount( 1, $findings );
		$this->assertTrue( ( new CloakedPluginState() )->ignoreIdentity( $findings[ 0 ]->identityKey(), $findings ) );
		$before = $this->snapshotSelectedOptions( [
			CloakedPluginState::FINDINGS_OPT_KEY, CloakedPluginState::IGNORE_OPT_KEY, CloakedPluginState::OPT_KEY,
		] );
		$con = $this->requireController();
		$con->opts->optSet( 'instant_alerts_data', [
			AlertHandlerCloakedPlugins::class => [ 'hidden_plugins' => [ $findings[ 0 ]->toAlertData() ] ],
		] );
		$this->resetCloakedPluginFindingsCache();
		$this->withWordPressVersion( '6.2.9', function () use ( $con, $before ) :void {
			$disabled = new CloakedPluginsCon();
			$disabled->execute();
			$this->assertFalse( has_filter( 'plugins_list', [ $disabled, 'observePluginsList' ] ) );
			$this->assertFalse( has_action( 'activated_plugin', [ $disabled, 'triggerDetection' ] ) );
			$this->assertSame( [], $disabled->detect() );
			$this->assertSame( [], $disabled->currentFindings() );
			( new Processor() )->runHourlyCron();
			$this->assertSame( [], $this->observeRealPluginsTable() );
			$this->assertSame( $before, $this->snapshotSelectedOptions( \array_keys( $before ) ) );

			$send = new \ReflectionMethod( $con->comps->instant_alerts, 'sendAlerts' );
			$send->setAccessible( true );
			$send->invoke( $con->comps->instant_alerts );
			$this->assertSame( [], $con->opts->optGet( 'instant_alerts_data' ) );
			$this->assertCount( 1, $this->capturedMails(), 'Only the original supported-version alert may have been sent.' );
			$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		} );
	}

	public function testNetworkPluginsTableDetectsAndClearsCloaking() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-network', 'Network plugin' );
		$active = get_site_option( 'active_sitewide_plugins', [] );
		update_site_option( 'active_sitewide_plugins', [ $file => time() ] );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		$this->captureShieldEvents();
		try {
			$this->assertArrayNotHasKey( $file, $this->prepareRealPluginsTable( true ) );
			$findings = $this->requireController()->comps->hidden_plugins->currentFindings();
			$this->assertCount( 1, $findings );
			$this->assertTrue( $findings[ 0 ]->networkActive );
			$this->assertCloakedPluginEvent( $file, 'all_plugins' );
			remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
			$this->assertArrayHasKey( $file, $this->prepareRealPluginsTable( true ) );
			$this->assertCount( 0, $this->requireController()->comps->hidden_plugins->currentFindings() );
			$this->assertCount( 1, $this->capturedMails() );
		}
		finally {
			update_site_option( 'active_sitewide_plugins', $active );
		}
	}

	private function withWordPressVersion( string $version, callable $test ) :void {
		$snapshot = ServicesState::snapshot();
		$general = $this->createPartialMock( General::class, [ 'getVersion' ] );
		$general->method( 'getVersion' )->willReturn( $version );
		ServicesState::mergeItems( [ 'service_wpgeneral' => $general ] );
		$this->resetInstantAlertHandlers();
		try {
			$test();
		}
		finally {
			ServicesState::restore( $snapshot );
			$this->resetInstantAlertHandlers();
		}
	}

	public function testReconciledStateIsAvailableWhenFindingEventIsPublished() :void {
		global $pagenow;
		$pagenow = 'plugins.php';

		$con = $this->requireController();
		$con->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->store();
		$this->resetInstantAlertHandlers();
		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-event-state', 'SHI Cloaked Event State' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );

		$publishedState = null;
		$stateObserver = function ( string $event ) use ( $con, &$publishedState ) :void {
			if ( $event === 'plugin_hidden_detected' ) {
				$publishedState = $con->comps->hidden_plugins->currentState();
			}
		};
		add_action( 'shield/event', $stateObserver, 1 );

		try {
			$this->observeRealPluginsTable();

			$this->assertIsArray( $publishedState );
			$this->assertCount( 1, $publishedState[ 'active' ] );
			$this->assertSame( $pluginFile, $publishedState[ 'active' ][ 0 ]->entry->file );
		}
		finally {
			remove_action( 'shield/event', $stateObserver, 1 );
		}
	}

	public function testMustUsePluginCloakedByShowAdvancedPluginsFilterFiresEvent() :void {
		global $pagenow;
		$pagenow = 'plugins.php';

		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$pluginFile = $this->createMustUseCloakedPlugin( 'shi-cloaked-mu.php', 'SHI Cloaked MU' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000, 2 );

		$this->captureShieldEvents();

		$this->observeRealPluginsTable();
		$this->assertCloakedPluginEvent( $pluginFile, 'show_advanced_plugins' );
		$this->assertCount( 0, $this->capturedMails() );
	}

	public function testPluginRemovedByPluginsListFilterFiresEvent() :void {
		global $pagenow;
		$pagenow = 'plugins.php';

		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-list', 'SHI Cloaked List' );
		add_filter( 'plugins_list', [ $this, 'hideCloakedPluginFromPluginsList' ], 1000 );

		$this->captureShieldEvents();

		$this->observeRealPluginsTable();
		$this->assertCloakedPluginEvent( $pluginFile, 'plugins_list' );
	}

	public function testNeutralPluginsListObserverDetectsCloakedFinalListWithoutMutatingList() :void {
		$GLOBALS[ 'pagenow' ] = 'plugins.php';
		$file = $this->createStandardCloakedPlugin( 'shi-cloaked-observer', 'Observer' );
		add_filter( 'plugins_list', [ $this, 'hideCloakedPluginFromPluginsList' ], 1000 );
		$before = null;
		$after = null;
		$captureBefore = static function ( array $plugins ) use ( &$before ) :array {
			$before = $plugins;
			return $plugins;
		};
		$captureAfter = static function ( array $plugins ) use ( &$after ) :array {
			$after = $plugins;
			return $plugins;
		};
		add_filter( 'plugins_list', $captureBefore, 1001 );
		add_filter( 'plugins_list', $captureAfter, PHP_INT_MAX );
		$this->captureShieldEvents();
		try {
			$this->prepareRealPluginsTable();
			$this->assertIsArray( $before );
			$this->assertIsArray( $after );
			$this->assertArrayHasKey( $file, $after[ 'cloaked' ], 'Shield rows must use the newly observed result.' );
			unset( $after[ 'cloaked' ] );
			$this->assertSame( $before, $after, 'Observation must not change WordPress plugin buckets.' );
			$this->assertCloakedPluginEvent( $file, 'plugins_list' );
		}
		finally {
			remove_filter( 'plugins_list', $captureBefore, 1001 );
			remove_filter( 'plugins_list', $captureAfter, PHP_INT_MAX );
		}
	}

	private function assertCloakedPluginEvent( string $pluginFile, string $reason ) :void {
		$events = $this->getCapturedEventsByKey( 'plugin_hidden_detected' );
		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'audit_params', $events[ 0 ][ 'meta' ] );
		$auditParams = $events[ 0 ][ 'meta' ][ 'audit_params' ];
		$this->assertIsArray( $auditParams );
		$this->assertArrayHasKey( 'plugin', $auditParams );
		$this->assertArrayHasKey( 'hidden_by', $auditParams );
		$this->assertSame( $pluginFile, $auditParams[ 'plugin' ] );
		$this->assertStringContainsString( $reason, (string)$auditParams[ 'hidden_by' ] );
	}


	private function lastCloakedPluginEventFile() :string {
		$events = $this->getCapturedEventsByKey( 'plugin_hidden_detected' );
		$event = \end( $events );
		return (string)( $event[ 'meta' ][ 'audit_params' ][ 'plugin' ] ?? '' );
	}

	private function resetInstantAlertHandlers() :void {
		$alertsProperty = new \ReflectionProperty( $this->requireController()->comps->instant_alerts, 'alerts' );
		$alertsProperty->setAccessible( true );
		$alertsProperty->setValue( $this->requireController()->comps->instant_alerts, null );
	}
}
