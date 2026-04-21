<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGeneralSettingsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class ConfigureGeneralSettingsScopeResolverTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_resolve_returns_leftover_scope_for_zone_backed_definitions() :void {
		$this->installZonesController( [
			'module_secadmin' => [ 'pin_toggle', 'admin_access_restrict_plugins', 'admin_access_restrict_plugins' ],
		] );

		$scope = ( new ConfigureGeneralSettingsScopeResolver() )->resolve(
			$this->newZone( [ 'module_secadmin' ] ),
			[ 'pin_toggle' ]
		);

		$this->assertSame( [ 'module_secadmin' ], $scope[ 'zone_component_slugs' ] ?? [] );
		$this->assertSame( [ 'admin_access_restrict_plugins' ], $scope[ 'option_keys' ] ?? [] );
	}

	public function test_resolve_returns_empty_when_no_leftovers_or_no_zone_owner_exist() :void {
		$this->installZonesController( [
			'module_login' => [ 'rename_wplogin_path' ],
		] );

		$resolver = new ConfigureGeneralSettingsScopeResolver();
		$this->assertSame( [], $resolver->resolve(
			$this->newZone( [ 'module_login' ] ),
			[ 'rename_wplogin_path' ]
		) );
		$this->assertSame( [], $resolver->resolve(
			$this->newZone( [] ),
			[ 'rename_wplogin_path' ]
		) );
		$this->assertSame( [], $resolver->resolve(
			null,
			[ 'rename_wplogin_path' ]
		) );
	}

	public function test_resolve_dedupes_leftovers_when_visible_components_overlap() :void {
		$this->installZonesController( [
			'module_scans' => [ 'scan_frequency', 'scan_path_exclusions', 'optimise_scan_speed', 'optimise_scan_speed' ],
		] );

		$scope = ( new ConfigureGeneralSettingsScopeResolver() )->resolve(
			$this->newZone( [ 'module_scans' ] ),
			[ 'scan_frequency', 'scan_path_exclusions', 'scan_path_exclusions' ]
		);

		$this->assertSame( [ 'module_scans' ], $scope[ 'zone_component_slugs' ] ?? [] );
		$this->assertSame( [ 'optimise_scan_speed' ], $scope[ 'option_keys' ] ?? [] );
	}

	public function test_resolve_excludes_users_and_firewall_options_now_owned_by_visible_rows() :void {
		$this->installZonesController( [
			'module_users'    => [ 'manual_suspend', 'auto_password' ],
			'module_firewall' => [ 'block_send_email', 'clean_wp_rubbish' ],
		] );

		$resolver = new ConfigureGeneralSettingsScopeResolver();
		$this->assertSame( [], $resolver->resolve(
			$this->newZone( [ 'module_users' ] ),
			[ 'manual_suspend', 'auto_password' ]
		) );
		$this->assertSame(
			[ 'clean_wp_rubbish' ],
			$resolver->resolve(
				$this->newZone( [ 'module_firewall' ] ),
				[ 'block_send_email' ]
			)[ 'option_keys' ] ?? []
		);
	}

	private function installZonesController( array $optionsBySlug ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'zones' => new class( $optionsBySlug ) extends SecurityZonesCon {
				private array $optionsBySlug;

				public function __construct( array $optionsBySlug ) {
					$this->optionsBySlug = $optionsBySlug;
				}

				public function getZone( string $slug ) :Zone\Base {
					throw new \BadMethodCallException( 'Not used in this test.' );
				}

				public function getZoneComponent( string $slug ) :Component\Base {
					return new class( $this->optionsBySlug[ $slug ] ?? [] ) extends Component\Base {
						private array $localOptions;

						public function __construct( array $options ) {
							$this->localOptions = $options;
						}

						public function getOptions() :array {
							return $this->localOptions;
						}
					};
				}

				public function getComponentsForZone( Zone\Base $zone ) :array {
					throw new \BadMethodCallException( 'Not used in this test.' );
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function newZone( array $zoneComponentSlugs ) :Zone\Base {
		return new class( $zoneComponentSlugs ) extends Zone\Base {
			private array $localZoneComponentSlugs;

			public function __construct( array $zoneComponentSlugs ) {
				$this->localZoneComponentSlugs = $zoneComponentSlugs;
			}

			public function getConfigZoneComponentSlugs() :array {
				return $this->localZoneComponentSlugs;
			}
		};
	}
}
