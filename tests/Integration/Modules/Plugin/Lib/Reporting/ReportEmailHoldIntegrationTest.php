<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\MaintenanceQueueItemDisplayNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps\ReportEmail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportEmailHold;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class ReportEmailHoldIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	private array $originalQuery = [];

	public function set_up() {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'installation_time',
			'block_send_email_address',
		] );
		$this->originalQuery = Services::Request()->query;
		$this->startHold();
	}

	public function tear_down() {
		Services::Request()->query = $this->originalQuery;
		\remove_all_filters( $this->requireController()->prefix( 'bypass_is_plugin_admin' ) );
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_hold_lasts_one_day_from_install_and_only_while_no_report_email_is_chosen() :void {
		$con = $this->requireController();
		$now = Services::Request()->ts();

		$this->assertTrue( ( new ReportEmailHold() )->isActive() );

		$con->opts->optSet( 'installation_time', $now - ReportEmailHold::WINDOW );
		$this->assertFalse( ( new ReportEmailHold() )->isActive(), 'The hold ends a day after install.' );

		$con->opts->optSet( 'installation_time', 0 );
		$this->assertFalse( ( new ReportEmailHold() )->isActive(), 'No install time means no hold.' );

		$con->opts->optSet( 'installation_time', $now - 60 )->optSet( 'block_send_email_address', 'agency@example.test' );
		$this->assertFalse( ( new ReportEmailHold() )->isActive(), 'A chosen report email ends the hold.' );

		$con->opts->optSet( 'block_send_email_address', '' );
		$this->assertTrue( ( new ReportEmailHold() )->isActive(), 'Clearing the report email restores the hold.' );
		$this->assertSame( '', $con->comps->opts_lookup->getChosenReportEmail() );
		$this->assertSame(
			Services::WpGeneral()->getSiteAdminEmail(),
			$con->comps->opts_lookup->getReportEmail(),
			'Reports still fall back to the site admin email.'
		);
	}

	public function test_report_email_filter_is_a_choice_on_free_sites_and_the_reporting_status_agrees() :void {
		$con = $this->requireController();
		$this->assertFalse( $con->isPremiumActive() );
		$this->assertSame( EnumEnabledStatus::OKAY, ( new Reporting() )->enabledStatus() );

		$filter = static fn() :string => 'agency@example.test';
		\add_filter( 'shield/report_email', $filter );
		try {
			$this->assertSame( 'agency@example.test', $con->comps->opts_lookup->getReportEmail() );
			$this->assertFalse( ( new ReportEmailHold() )->isActive() );
			$this->assertSame( EnumEnabledStatus::GOOD, ( new Reporting() )->enabledStatus() );
			$this->assertTrue( ( new ReportEmail() )->skipStep(), 'The setup step counts a filtered address as chosen.' );
		}
		finally {
			\remove_filter( 'shield/report_email', $filter );
		}
	}

	public function test_choosing_the_site_admin_email_saves_it_and_ends_the_hold() :void {
		$resp = ( new ReportEmail() )->processStepFormSubmit( [ 'use_site_admin_email' => 'Y' ] );

		$this->assertTrue( $resp->success );
		$this->assertSame(
			Services::WpGeneral()->getSiteAdminEmail(),
			$this->requireController()->opts->optGet( 'block_send_email_address' )
		);
		$this->assertFalse( ( new ReportEmailHold() )->isActive() );
	}

	public function test_typed_report_email_is_saved() :void {
		( new ReportEmail() )->processStepFormSubmit( [ 'ReportEmail' => ' agency@example.test ' ] );

		$this->assertSame( 'agency@example.test', $this->requireController()->opts->optGet( 'block_send_email_address' ) );
		$this->assertFalse( ( new ReportEmailHold() )->isActive() );
	}

	public function test_actions_queue_item_leads_to_the_setup_guide_and_cannot_be_ignored() :void {
		$provider = new MaintenanceIssueStateProvider();
		$state = $provider->buildStates()[ 'report_email' ] ?? null;

		$this->assertIsArray( $state );
		$this->assertSame( 1, $state[ 'count' ] );
		$this->assertSame( 'review', $state[ 'drill_bucket' ] );
		$this->assertFalse( $provider->canBeIgnored( 'report_email' ) );

		$item = $this->normalizedItem( $state );
		$this->assertSame( [], $item[ 'toggle_action' ] );
		$this->assertSame( $this->requireController()->plugin_urls->setupGuide( ReportEmail::SLUG ), $item[ 'cta' ][ 'href' ] ?? '' );
		$this->assertStringContainsString( 'setup_step=report_email', $item[ 'cta' ][ 'href' ] ?? '' );

		$this->requireController()->opts->optSet( 'block_send_email_address', 'agency@example.test' );
		$this->assertArrayNotHasKey( 'report_email', ( new MaintenanceIssueStateProvider() )->buildStates() );
	}

	public function test_setup_guide_puts_report_email_first_and_opens_on_the_requested_step() :void {
		$this->loginAsAdministrator();
		\add_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), '__return_true' );
		Services::Request()->query = [ 'setup_guide' => 'open', 'setup_step' => 'security_admin' ];

		$guide = ( new SetupGuideDataBuilder() )->build();

		$this->assertTrue( $guide[ 'show' ] );
		$this->assertSame( [ 'report_email', 'security_admin' ], \array_slice( \array_column( $guide[ 'steps' ], 'slug' ), 0, 2 ) );
		$this->assertSame( 'security_admin', $guide[ 'open_step' ] );

		Services::Request()->query = [ 'setup_guide' => 'open', 'setup_step' => 'not_a_step' ];
		$this->assertSame( 'report_email', ( new SetupGuideDataBuilder() )->build()[ 'open_step' ] );
	}

	private function startHold() :void {
		$this->requireController()->opts
			->optSet( 'installation_time', Services::Request()->ts() - 60 )
			->optSet( 'block_send_email_address', '' );
	}

	/**
	 * Normalized against the live maintenance states, as the Actions Queue does.
	 */
	private function normalizedItem( array $state ) :array {
		$items = ( new MaintenanceQueueItemDisplayNormalizer() )->normalizeAll( [
			[
				'key'         => $state[ 'key' ],
				'zone'        => 'maintenance',
				'label'       => $state[ 'label' ],
				'count'       => $state[ 'count' ],
				'severity'    => $state[ 'severity' ],
				'description' => $state[ 'description' ],
				'href'        => $state[ 'href' ],
				'action'      => $state[ 'action' ],
				'target'      => $state[ 'target' ],
			],
		] );
		return $items[ 0 ];
	}
}
