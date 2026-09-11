<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\CloakedPluginState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\CloakedPluginFixtureTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class CloakedPluginsConIntegrationTest extends ShieldIntegrationTestCase {

	use CloakedPluginFixtureTrait;
	use LocalEmailCapture;

	private array $optionsSnapshot = [];
	private ?string $originalPageNow = null;

	public function set_up() {
		parent::set_up();
		global $pagenow;
		$this->originalPageNow = $pagenow ?? null;
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
		$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->detect() );
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
		$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->detect() );

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect( $this->normalFinalPluginsList() ) );

		$this->resetCloakedPluginFindingsCache();
		$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect() );
	}

	public function testResolvedFindingReappearsWithOneNewEventAndEmail() :void {
		global $pagenow;

		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-reappears', 'SHI Cloaked Reappears' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		$this->captureShieldEvents();

		$pagenow = 'plugins.php';
		$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->detect() );

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect( $this->normalFinalPluginsList() ) );

		$pagenow = 'plugins.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->detect() );
		$this->assertCount( 2, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 2, $this->capturedMails() );
		$this->assertSame( $pluginFile, $this->lastCloakedPluginEventFile() );
	}

	public function testDeletedPluginClearsPersistedFindingWithoutAuthoritativeList() :void {
		global $pagenow;

		$this->createStandardCloakedPlugin( 'shi-cloaked-deleted', 'SHI Cloaked Deleted' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );

		$pagenow = 'plugins.php';
		$this->assertCount( 1, $this->requireController()->comps->hidden_plugins->detect() );
		$this->removeCloakedPluginFixtures();

		$pagenow = 'admin.php';
		$this->resetCloakedPluginFindingsCache();
		$this->assertSame( [], $this->requireController()->comps->hidden_plugins->detect() );
	}

	public function testStandardPluginCloakedByAllPluginsFilterFiresEventAndDedupeAlert() :void {
		$con = $this->requireController();
		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-all', 'SHI Cloaked All Plugins' );
		add_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );

		$this->captureShieldEvents();

		$con->comps->hidden_plugins->detect();
		$this->assertCloakedPluginEvent( $pluginFile, 'all_plugins' );
		$this->assertCount( 1, $this->capturedMails() );
		$mail = $this->lastCapturedMail();
		$this->assertArrayHasKey( 'html_body', $mail );
		$this->assertHtmlContainsMarker(
			$pluginFile,
			(string)$mail[ 'html_body' ],
			'Cloaked plugin alert HTML body'
		);

		$con->comps->hidden_plugins->detect();
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function testCloakedPluginDetectionCapabilityIsAlwaysAvailable() :void {
		$this->assertTrue( $this->requireController()->caps->canDetectCloakedPlugins() );
	}

	public function testReconciledStateIsAvailableWhenFindingEventIsPublished() :void {
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
			$con->comps->hidden_plugins->detect();

			$this->assertIsArray( $publishedState );
			$this->assertCount( 1, $publishedState[ 'active' ] );
			$this->assertSame( $pluginFile, $publishedState[ 'active' ][ 0 ]->entry->file );
		}
		finally {
			remove_action( 'shield/event', $stateObserver, 1 );
		}
	}

	public function testMustUsePluginCloakedByShowAdvancedPluginsFilterFiresEvent() :void {
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$pluginFile = $this->createMustUseCloakedPlugin( 'shi-cloaked-mu.php', 'SHI Cloaked MU' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000, 2 );

		$this->captureShieldEvents();

		$this->requireController()->comps->hidden_plugins->detect();
		$this->assertCloakedPluginEvent( $pluginFile, 'show_advanced_plugins' );
		$this->assertCount( 0, $this->capturedMails() );
	}

	public function testPluginRemovedByPluginsListFilterFiresEvent() :void {
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-list', 'SHI Cloaked List' );
		add_filter( 'plugins_list', [ $this, 'hideCloakedPluginFromPluginsList' ], 1000 );

		$this->captureShieldEvents();

		$this->requireController()->comps->hidden_plugins->detect();
		$this->assertCloakedPluginEvent( $pluginFile, 'plugins_list' );
	}

	public function testNeutralPluginsListObserverDetectsCloakedFinalListWithoutMutatingList() :void {
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$pluginFile = $this->createStandardCloakedPlugin( 'shi-cloaked-observer', 'SHI Cloaked Observer' );
		$originalGet = $_GET;
		unset( $_GET[ 'plugin_status' ], $_GET[ 's' ] );

		try {
			$finalPluginsList = $this->hideCloakedPluginFromPluginsList( [
				'all'                  => \get_plugins(),
				'search'               => [],
				'active'               => [],
				'inactive'             => \get_plugins(),
				'recently_activated'   => [],
				'upgrade'              => [],
				'mustuse'              => [],
				'dropins'              => [],
				'paused'               => [],
				'auto-update-enabled'  => [],
				'auto-update-disabled' => [],
			] );

			$this->captureShieldEvents();

			$this->assertSame(
				$finalPluginsList,
				$this->requireController()->comps->hidden_plugins->observePluginsList( $finalPluginsList )
			);
			$this->assertCloakedPluginEvent( $pluginFile, 'plugins_list' );
		}
		finally {
			$_GET = $originalGet;
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

	private function normalFinalPluginsList() :array {
		return [
			'all'     => \get_plugins(),
			'mustuse' => \function_exists( 'get_mu_plugins' ) ? \get_mu_plugins() : [],
		];
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
