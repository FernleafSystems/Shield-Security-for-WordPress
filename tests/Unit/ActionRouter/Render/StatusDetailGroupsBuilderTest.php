<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\StatusDetailGroupsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class StatusDetailGroupsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_for_maintenance_merges_issues_with_good_assessments_only() :void {
		$groups = ( new StatusDetailGroupsBuilder() )->buildForMaintenance(
			[
				[
					'key'         => 'system_ssl_certificate',
					'label'       => 'SSL Certificate',
					'description' => 'SSL certificate requires review.',
					'count'       => 1,
					'severity'    => 'critical',
					'cta'         => [
						'href'   => '/admin/ssl',
						'label'  => 'Review',
						'target' => '_blank',
					],
				],
				[
					'key'         => 'wp_updates',
					'label'       => 'WordPress Version',
					'description' => 'An update is available.',
					'count'       => 1,
					'severity'    => 'warning',
					'cta'         => [
						'href'  => '/admin/updates',
						'label' => 'Open',
					],
				],
			],
			[
				[
					'key'               => 'wp_updates',
					'label'             => 'WordPress Version',
					'description'       => 'Update available.',
					'status'            => 'warning',
					'status_label'      => 'Warning',
					'status_icon_class' => 'bi bi-exclamation-circle-fill',
				],
				[
					'key'               => 'system_lib_openssl',
					'label'             => 'OpenSSL Extension',
					'description'       => 'OpenSSL looks healthy.',
					'status'            => 'good',
					'status_label'      => 'Good',
					'status_icon_class' => 'bi bi-check-circle-fill',
				],
			]
		);

		$this->assertSame( [ 'critical', 'warning', 'good' ], \array_column( $groups, 'status' ) );
		$this->assertSame( [ 'system_ssl_certificate' ], \array_column( $groups[ 0 ][ 'rows' ] ?? [], 'key' ) );
		$this->assertSame( [ 'wp_updates' ], \array_column( $groups[ 1 ][ 'rows' ] ?? [], 'key' ) );
		$this->assertSame( [ 'system_lib_openssl' ], \array_column( $groups[ 2 ][ 'rows' ] ?? [], 'key' ) );
		$this->assertSame( '_blank', $groups[ 0 ][ 'rows' ][ 0 ][ 'action' ][ 'target' ] ?? '' );
		$this->assertSame( 1, $groups[ 0 ][ 'rows' ][ 0 ][ 'count_badge' ] ?? 0 );
	}

	public function test_build_for_configure_orders_by_severity_and_preserves_action_contract() :void {
		$groups = ( new StatusDetailGroupsBuilder() )->buildForConfigure(
			[
				[
					'title'             => 'Passive Logging',
					'note'              => 'Logs are enabled.',
					'status'            => 'good',
					'status_label'      => 'Active',
					'status_icon_class' => 'bi bi-check-circle-fill',
					'explanations'      => [],
					'config_action'     => [],
				],
				[
					'title'             => 'Primary Control',
					'note'              => 'Requires immediate setup.',
					'status'            => 'critical',
					'status_label'      => 'Issue',
					'status_icon_class' => 'bi bi-x-circle-fill',
					'explanations'      => [ 'Critical explanation' ],
					'config_action'     => [
						'href'    => 'javascript:{}',
						'icon'    => 'bi bi-gear',
						'classes' => [ 'zone_component_action' ],
						'data'    => [
							'zone_component_action' => 'offcanvas_zone_component_config',
						],
					],
				],
				[
					'title'             => 'Secondary Control',
					'note'              => 'Needs attention.',
					'status'            => 'warning',
					'status_label'      => 'Needs Work',
					'status_icon_class' => 'bi bi-exclamation-circle-fill',
					'explanations'      => [ 'Warning explanation' ],
					'config_action'     => [],
				],
				[
					'title'             => 'General Settings',
					'note'              => 'General configuration.',
					'status'            => 'neutral',
					'status_label'      => 'General',
					'status_icon_class' => 'bi bi-info-circle-fill',
					'explanations'      => [],
					'config_action'     => [],
				],
				[
					'title'             => 'Another Warning',
					'note'              => 'Also needs attention.',
					'status'            => 'warning',
					'status_label'      => 'Needs Work',
					'status_icon_class' => 'bi bi-exclamation-circle-fill',
					'explanations'      => [],
					'config_action'     => [],
				],
			]
		);

		$this->assertSame( [ 'critical', 'warning', 'good', 'neutral' ], \array_column( $groups, 'status' ) );
		$this->assertSame( [ 'Primary Control' ], \array_column( $groups[ 0 ][ 'rows' ] ?? [], 'title' ) );
		$this->assertSame( [ 'Secondary Control', 'Another Warning' ], \array_column( $groups[ 1 ][ 'rows' ] ?? [], 'title' ) );
		$this->assertSame( 'Configure', $groups[ 0 ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertSame( 'offcanvas_zone_component_config', $groups[ 0 ][ 'rows' ][ 0 ][ 'action' ][ 'data' ][ 'zone_component_action' ] ?? '' );
		$this->assertSame( [], $groups[ 2 ][ 'rows' ][ 0 ][ 'action' ] ?? null );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}
