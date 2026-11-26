<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\MaintenanceItemDetails;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Every to-do in the Actions Queue opens in a drawer that explains its check. The steps that put it right show only
 * while there's something to put right.
 */
class MaintenanceItemDrawerIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ MaintenanceIssueStateProvider::OPT_KEY ] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_every_todo_explains_what_it_checks_and_why() :void {
		$provider = new MaintenanceIssueStateProvider();
		foreach ( PluginNavs::actionsLandingAssessmentDefinitions() as $definition ) {
			if ( $definition[ 'zone' ] === 'maintenance' ) {
				$guidance = $provider->guidanceFor( $definition[ 'key' ] );
				$this->assertNotSame( '', $guidance[ 'checks' ] ?? '', $definition[ 'key' ] );
				$this->assertNotEmpty( $guidance[ 'why' ] ?? [], $definition[ 'key' ] );
			}
		}
	}

	public function test_a_todo_that_needs_doing_shows_its_steps_even_when_ignored() :void {
		if ( !get_user_by( 'login', 'admin' ) instanceof \WP_User ) {
			self::factory()->user->create( [
				'user_login' => 'admin',
				'role'       => 'administrator',
			] );
		}
		$firstStep = ( new MaintenanceIssueStateProvider() )->guidanceFor( 'default_admin_user' )[ 'steps' ][ 0 ];

		$this->assertStringContainsString( $firstStep, $this->drawerText( 'default_admin_user' ) );

		$this->requireController()->opts->optSet( MaintenanceIssueStateProvider::OPT_KEY, [
			'default_admin_user' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
		] )->store();
		$state = ( new MaintenanceIssueStateProvider() )->buildStates()[ 'default_admin_user' ];
		$this->assertSame( [ 0, 1 ], [ $state[ 'count' ], $state[ 'ignored_count' ] ] );
		$this->assertStringContainsString( $firstStep, $this->drawerText( 'default_admin_user' ) );
	}

	public function test_a_passing_check_explains_itself_without_steps() :void {
		// Which checks pass depends on the PHP and the WordPress these tests run on, so each one that does is tried.
		$provider = new MaintenanceIssueStateProvider();
		foreach ( $provider->buildStates() as $key => $state ) {
			$guidance = $provider->guidanceFor( $key );
			$drawer = $this->drawerText( $key );
			$this->assertStringContainsString( $guidance[ 'checks' ], $drawer, $key );
			if ( $state[ 'count' ] === 0 && $state[ 'ignored_count' ] === 0 && !empty( $guidance[ 'steps' ] ) ) {
				$this->assertStringNotContainsString( $guidance[ 'steps' ][ 0 ], $drawer, $key );
			}
		}
	}

	private function drawerText( string $maintenanceKey ) :string {
		return \html_entity_decode(
			$this->requireController()->action_router->render( MaintenanceItemDetails::class, [
				'maintenance_key' => $maintenanceKey,
			] ),
			\ENT_QUOTES
		);
	}
}
