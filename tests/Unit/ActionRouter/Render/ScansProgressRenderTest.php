<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ScansProgressRenderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_n' )->alias( static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural );
		Functions\when( 'rawurlencode_deep' )->returnArg( 1 );
		Functions\when( 'add_query_arg' )->alias( static fn( array $args, string $url ) :string => $url.'?'.\http_build_query( $args ) );
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_running_rows_follow_the_scan_order_with_each_scans_status_and_scope() :void {
		$rows = $this->render( ScansProgress::STATE_RUNNING, [
			$this->activeRow( [ 'id' => 12, 'scan' => 'wpv', 'name' => 'Vulnerability Scan', 'scope_type' => 'plugin', 'scope_key' => 'shield-security', 'progress' => 40 ] ),
			$this->activeRow( [ 'id' => 11, 'scan' => 'afs', 'name' => 'File Guard', 'display_status' => 'waiting', 'progress' => 0 ] ),
		], [ 'wpv', 'afs' ] )[ 'vars' ][ 'page_rows' ];

		$this->assertSame( [
			[
				'id'                   => 11,
				'name'                 => 'File Guard',
				'scope_label'          => '',
				'status'               => 'waiting',
				'status_label'         => 'waiting',
				'can_attempt_recovery' => false,
				'progress'             => 0,
				'aria_label'           => 'File Guard progress',
			],
			[
				'id'                   => 12,
				'name'                 => 'Vulnerability Scan',
				'scope_label'          => 'Plugin: shield-security',
				'status'               => 'running',
				'status_label'         => 'running',
				'can_attempt_recovery' => false,
				'progress'             => 40,
				'aria_label'           => 'Vulnerability Scan (Plugin: shield-security) progress',
			],
		], $rows );
	}

	public function test_scans_of_one_kind_for_different_scopes_keep_their_own_rows_and_can_be_told_apart() :void {
		$vars = $this->render( ScansProgress::STATE_RUNNING, [
			$this->activeRow( [ 'id' => 21, 'scope_type' => 'plugin', 'scope_key' => 'akismet', 'progress' => 70 ] ),
			$this->activeRow( [ 'id' => 22, 'scope_type' => 'theme', 'scope_key' => 'twentytwenty', 'display_status' => 'waiting', 'progress' => 0 ] ),
		], [ 'afs' ] )[ 'vars' ];
		$rows = $vars[ 'page_rows' ];

		$this->assertSame( [ 21, 22 ], \array_column( $rows, 'id' ) );
		$this->assertSame( [ 'Plugin: akismet', 'Theme: twentytwenty' ], \array_column( $rows, 'scope_label' ) );
		$this->assertSame(
			[ 'File Guard (Plugin: akismet) progress', 'File Guard (Theme: twentytwenty) progress' ],
			\array_column( $rows, 'aria_label' )
		);
		$this->assertSame( 'Current Scan: File Guard (Plugin: akismet)', $vars[ 'heading' ] );
		$this->assertSame( 'Current Scan: File Guard (Plugin: akismet) 70%', $vars[ 'announcement' ] );
	}

	public function test_scan_label_names_the_scope_only_when_the_scan_is_limited_to_one() :void {
		$this->assertSame( 'File Guard', ScansProgress::ScanLabel( $this->activeRow() ) );
		$this->assertSame( 'File Guard (Core)', ScansProgress::ScanLabel( $this->activeRow( [ 'scope_type' => 'core' ] ) ) );
		$this->assertSame(
			'File Guard (Theme: twentytwenty)',
			ScansProgress::ScanLabel( $this->activeRow( [ 'scope_type' => 'theme', 'scope_key' => 'twentytwenty' ] ) )
		);
	}

	public function test_running_row_offers_recovery_for_a_stalled_scan() :void {
		$rows = $this->render( ScansProgress::STATE_RUNNING, [
			$this->activeRow( [
				'id'                   => 13,
				'scan'                 => 'apc',
				'name'                 => 'Asset Scan',
				'scope_type'           => 'core',
				'display_status'       => 'stalled',
				'is_stale'             => true,
				'can_attempt_recovery' => true,
				'progress'             => 10,
			] ),
		], [ 'apc' ] )[ 'vars' ][ 'page_rows' ];

		$this->assertCount( 1, $rows );
		$this->assertSame( 'stalled', $rows[ 0 ][ 'status' ] );
		$this->assertSame( 'appears stalled', $rows[ 0 ][ 'status_label' ] );
		$this->assertSame( 'Core', $rows[ 0 ][ 'scope_label' ] );
		$this->assertTrue( $rows[ 0 ][ 'can_attempt_recovery' ] );
	}

	public function test_scans_given_that_are_no_longer_active_show_as_finished() :void {
		$vars = $this->render( ScansProgress::STATE_RUNNING, [
			$this->activeRow( [ 'id' => 12, 'scan' => 'wpv', 'name' => 'Vulnerability Scan', 'progress' => 30 ] ),
		], [ 'afs', 'wpv', 'apc' ] )[ 'vars' ];
		$rows = $vars[ 'page_rows' ];

		$this->assertSame( [ 'finished', 'running', 'finished' ], \array_column( $rows, 'status' ) );
		$this->assertSame( [ 'finished', 'running', 'finished' ], \array_column( $rows, 'status_label' ) );
		$this->assertSame( [ 100, 30, 100 ], \array_column( $rows, 'progress' ) );
		$this->assertSame( [ 0, 12, 0 ], \array_column( $rows, 'id' ) );
		$this->assertSame( 'Name of afs', $rows[ 0 ][ 'name' ] );
		$this->assertSame( 67, $vars[ 'overall_progress' ] );
	}

	public function test_running_is_headed_by_the_current_scan_and_announces_how_far_it_has_got() :void {
		$vars = $this->render( ScansProgress::STATE_RUNNING, [
			$this->activeRow( [ 'name' => 'File Guard', 'progress' => 37 ] ),
			$this->activeRow( [ 'id' => 2, 'scan' => 'wpv', 'name' => 'Vulnerability Scan', 'display_status' => 'waiting' ] ),
		], [ 'afs', 'wpv' ] )[ 'vars' ];

		$this->assertSame( ScansProgress::STATE_RUNNING, $vars[ 'state' ] );
		$this->assertSame( 'Current Scan: File Guard', $vars[ 'heading' ] );
		$this->assertSame( 'Current Scan: File Guard 37%', $vars[ 'announcement' ] );
		$this->assertTrue( $vars[ 'is_busy' ] );
	}

	public function test_preparing_is_busy_and_says_so() :void {
		$vars = $this->render( ScansProgress::STATE_INITIATING )[ 'vars' ];

		$this->assertSame( ScansProgress::STATE_INITIATING, $vars[ 'state' ] );
		$this->assertSame( 'Preparing scans.', $vars[ 'heading' ] );
		$this->assertSame( 'Preparing scans.', $vars[ 'announcement' ] );
		$this->assertTrue( $vars[ 'is_busy' ] );
	}

	public function test_failure_gives_its_reason_and_a_way_back_to_choosing() :void {
		$vars = $this->render( ScansProgress::STATE_FAILED, [], [], 'The scan stopped.' )[ 'vars' ];

		$this->assertFalse( $vars[ 'is_busy' ] );
		$this->assertSame( 'Scan failed. The scan stopped.', $vars[ 'announcement' ] );
		$this->assertSame( [
			'tone'       => 'is-critical',
			'icon'       => 'bi-x-lg',
			'title'      => 'Scan failed.',
			'message'    => 'The scan stopped.',
			'href'       => '/scans-run/?step=choose',
			'link_label' => 'Try again',
		], $vars[ 'notice' ] );
	}

	public function test_stale_says_the_scans_may_still_be_running_and_offers_a_refresh() :void {
		$vars = $this->render( ScansProgress::STATE_STALE )[ 'vars' ];

		$this->assertFalse( $vars[ 'is_busy' ] );
		$this->assertSame( "Scan progress couldn't be updated. Your scans may still be running.", $vars[ 'announcement' ] );
		$this->assertSame( 'is-warning', $vars[ 'notice' ][ 'tone' ] );
		$this->assertSame( 'Your scans may still be running.', $vars[ 'notice' ][ 'message' ] );
		$this->assertSame( '/scans-run/', $vars[ 'notice' ][ 'href' ] );
		$this->assertSame( 'Refresh', $vars[ 'notice' ][ 'link_label' ] );
	}

	public function test_unknown_state_is_refused() :void {
		$this->expectException( \UnexpectedValueException::class );
		$this->render( 'modal' );
	}

	/**
	 * @param list<array<string,mixed>> $scanRows
	 * @param list<string>              $scanSlugs
	 */
	private function render( string $state, array $scanRows = [], array $scanSlugs = [], string $failureMessage = '' ) :array {
		return ( new ScansProgressRenderTestDouble( [
			'state'           => $state,
			'scan_rows'       => $scanRows,
			'scan_slugs'      => $scanSlugs,
			'failure_message' => $failureMessage,
		] ) )->renderDataForTest();
	}

	private function activeRow( array $overrides = [] ) :array {
		return \array_merge( [
			'id'                   => 1,
			'scan'                 => 'afs',
			'name'                 => 'File Guard',
			'scope_type'           => 'full',
			'scope_key'            => '',
			'raw_status'           => 'running',
			'display_status'       => 'running',
			'is_current'           => true,
			'is_stale'             => false,
			'can_attempt_recovery' => false,
			'progress'             => 0,
			'total_items'          => 0,
			'unfinished'           => 0,
		], $overrides );
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function actionsQueueScans() :string {
				return '/actions-queue-scans/';
			}

			public function scansRun() :string {
				return '/scans-run/';
			}
		};
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) :object {
					return new class( $slug ) {
						private string $slug;

						public function __construct( string $slug ) {
							$this->slug = $slug;
						}

						public function getStrings() :array {
							return [ 'name' => 'Name of '.$this->slug ];
						}
					};
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}
}

class ScansProgressRenderTestDouble extends ScansProgress {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}
