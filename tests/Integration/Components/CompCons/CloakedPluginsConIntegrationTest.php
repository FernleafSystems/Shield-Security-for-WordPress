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

	public function set_up() {
		parent::set_up();
		$this->startLocalEmailCapture();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'instant_alert_hidden_plugins',
			'instant_alerts_data',
			CloakedPluginState::OPT_KEY,
			'block_send_email_address',
		] );
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'email' )
			->optSet( 'instant_alerts_data', [] )
			->optSet( CloakedPluginState::OPT_KEY, [] )
			->optSet( 'block_send_email_address', 'cloaked-plugins@example.com' )
			->store();
		$this->resetInstantAlertHandlers();
	}

	public function tear_down() {
		$this->removeCloakedPluginFixtureFilters();
		$this->cleanupCloakedPluginFixtures();
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->resetInstantAlertHandlers();
		}
		parent::tear_down();
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

	private function resetInstantAlertHandlers() :void {
		$alertsProperty = new \ReflectionProperty( $this->requireController()->comps->instant_alerts, 'alerts' );
		$alertsProperty->setAccessible( true );
		$alertsProperty->setValue( $this->requireController()->comps->instant_alerts, null );
	}
}
