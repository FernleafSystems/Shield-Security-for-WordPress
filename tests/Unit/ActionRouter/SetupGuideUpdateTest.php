<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SetupGuideUpdate;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\SecurityAdminRequiredException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Users;

class SetupGuideUpdateTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \preg_replace( '/[^a-z0-9_\-]/', '', $text ) ) : ''
		);
		Functions\when( 'user_can' )->justReturn( true );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_skip_records_the_step_once_as_skipped_and_dismiss_hides_the_guide() :void {
		$opts = $this->installEnvironment();

		foreach ( [ 'security_admin', 'security_admin' ] as $slug ) {
			$action = new SetupGuideUpdate( [ 'op' => 'skip', 'step_slug' => $slug ] );
			$action->process();
			$this->assertTrue( $action->response()->payload()[ 'success' ] );
		}
		$state = new SetupGuideState();
		$this->assertSame( [ 'security_admin' ], $state->skippedSlugs() );
		$this->assertSame( [], $state->doneSlugs(), 'A skipped step is not a done one.' );
		$this->assertFalse( $state->isDismissed() );

		$action = new SetupGuideUpdate( [ 'op' => 'dismiss' ] );
		$action->process();
		$this->assertTrue( ( new SetupGuideState() )->isDismissed() );
		$this->assertSame( 1700001234, $opts->optGet( SetupGuideState::OPT_KEY )[ 'dismissed_at' ] ?? 0 );
	}

	public function test_minimise_is_recorded_without_closing_the_guide() :void {
		$this->installEnvironment();
		$this->assertFalse( ( new SetupGuideState() )->isMinimised() );

		$action = new SetupGuideUpdate( [ 'op' => 'minimise' ] );
		$action->process();

		$this->assertTrue( $action->response()->payload()[ 'success' ] );
		$this->assertTrue( ( new SetupGuideState() )->isMinimised() );
		$this->assertFalse( ( new SetupGuideState() )->isDismissed() );
	}

	public function test_a_step_is_done_or_skipped_and_saving_wins() :void {
		$this->installEnvironment();
		$state = new SetupGuideState();

		$this->assertSame( SetupGuideState::STEP_TODO, $state->stepState( 'security_admin', false ) );
		$this->assertSame( SetupGuideState::STEP_ALREADY, $state->stepState( 'security_admin', true ) );

		$state->markSkipped( 'security_admin' );
		$this->assertSame( SetupGuideState::STEP_SKIPPED, $state->stepState( 'security_admin', false ) );
		$this->assertSame( SetupGuideState::STEP_ALREADY, $state->stepState( 'security_admin', true ), 'A skipped step whose setting is since in place is set up.' );

		$state->markDone( 'security_admin' );
		$this->assertSame( SetupGuideState::STEP_DONE, $state->stepState( 'security_admin', false ) );
		$this->assertSame( [], $state->skippedSlugs() );

		$this->assertTrue( $state->markSkipped( 'security_admin' ) );
		$this->assertSame( SetupGuideState::STEP_DONE, $state->stepState( 'security_admin', false ), 'Skipping a saved step leaves it done.' );
	}

	public function test_a_recorded_licence_step_is_skipped_not_done_while_there_is_no_licence() :void {
		$this->installEnvironment();
		$state = new SetupGuideState();

		$state->markDone( 'license' );

		$this->assertSame( SetupGuideState::STEP_SKIPPED, $state->stepState( 'license', false ) );
	}

	public function test_where_reports_go_cannot_be_skipped_and_is_only_done_by_being_set() :void {
		$opts = $this->installEnvironment();

		$action = new SetupGuideUpdate( [ 'op' => 'skip', 'step_slug' => 'report_email' ] );
		$action->process();
		$this->assertFalse( $action->response()->payload()[ 'success' ] );
		$this->assertSame( [], $opts->optGet( SetupGuideState::OPT_KEY ) );

		// A record of the step from when it could be skipped doesn't stand in for an address.
		$state = new SetupGuideState();
		$state->markDone( 'report_email' );
		$this->assertSame( SetupGuideState::STEP_TODO, $state->stepState( 'report_email', false ) );
		$this->assertSame( SetupGuideState::STEP_DONE, $state->stepState( 'report_email', true ) );
	}

	public function test_skip_records_only_the_guides_own_steps() :void {
		$opts = $this->installEnvironment();

		foreach ( [ 'not_a_step', 'welcome', 'SECURITY_ADMIN', '' ] as $slug ) {
			$action = new SetupGuideUpdate( [ 'op' => 'skip', 'step_slug' => $slug ] );
			$action->process();
			$this->assertFalse( $action->response()->payload()[ 'success' ], $slug );
		}
		$this->assertSame( [], $opts->optGet( SetupGuideState::OPT_KEY ) );
	}

	public function test_unknown_operation_changes_nothing() :void {
		$opts = $this->installEnvironment();

		$action = new SetupGuideUpdate( [ 'op' => 'reset_everything' ] );
		$action->process();

		$this->assertFalse( $action->response()->payload()[ 'success' ] );
		$this->assertSame( [], $opts->optGet( SetupGuideState::OPT_KEY ) );
	}

	public function test_an_admin_without_security_admin_cannot_change_the_sites_progress() :void {
		$opts = $this->installEnvironment( false );

		foreach ( [ [ 'op' => 'skip', 'step_slug' => 'security_admin' ], [ 'op' => 'minimise' ], [ 'op' => 'dismiss' ] ] as $data ) {
			try {
				( new SetupGuideUpdate( $data ) )->process();
				$this->fail( 'The update went ahead without Security Admin.' );
			}
			catch ( SecurityAdminRequiredException $e ) {
				$this->assertSame( [], $opts->optGet( SetupGuideState::OPT_KEY ) );
			}
		}
	}

	private function installEnvironment( bool $isSecurityAdmin = true ) :SetupGuideUpdateOptsStub {
		$opts = new SetupGuideUpdateOptsStub();

		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700001234 ),
			'service_wpusers' => new class extends Users {
				public function isUserLoggedIn() :bool {
					return true;
				}

				public function getCurrentWpUser() {
					return (object)[ 'ID' => 1 ];
				}
			},
		] );
		PluginControllerInstaller::install( new SetupGuideUpdateControllerStub( $opts, $isSecurityAdmin ) );
		return $opts;
	}

}

class SetupGuideUpdateControllerStub extends Controller {

	public function __construct( SetupGuideUpdateOptsStub $opts, bool $isSecurityAdmin ) {
		$this->cfg = (object)[
			'properties' => [
				'base_permissions' => 'manage_options',
			],
		];
		$this->this_req = (object)[
			'request_bypasses_all_restrictions' => false,
			'is_ip_blocked'                     => false,
			'wp_is_ajax'                        => true,
			'is_security_admin'                 => $isSecurityAdmin,
		];
		$this->opts = $opts;
	}
}

/**
 * Site options, as the guide's progress is kept for the whole site rather than for one admin.
 */
class SetupGuideUpdateOptsStub {

	private array $values = [];

	public function optGet( string $key ) :array {
		return $this->values[ $key ] ?? [];
	}

	public function optSet( string $key, array $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}
}
