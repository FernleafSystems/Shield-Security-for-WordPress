<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\ZoneRenderDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ZoneRenderDataBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_get_zones_indexed_returns_expected_payload() :void {
		$builder = new ZoneRenderDataBuilder();
		$this->assertSame(
			[
				'secadmin' => [
					'slug'       => 'secadmin',
					'label'      => 'Security Admin',
					'icon_class' => 'bi bi-shield-fill',
					'href'       => '/admin/zones/secadmin',
				],
				'firewall' => [
					'slug'       => 'firewall',
					'label'      => 'Firewall',
					'icon_class' => 'bi bi-shield-shaded',
					'href'       => '/admin/zones/firewall',
				],
			],
			$builder->getZonesIndexed()
		);
	}

	public function test_get_zone_links_and_slugs_follow_indexed_source() :void {
		$builder = new ZoneRenderDataBuilder();
		$indexed = $builder->getZonesIndexed();
		$links = $builder->getZoneLinks();
		$slugs = $builder->getZoneSlugs();

		$this->assertSame( \array_keys( $indexed ), $slugs );
		$this->assertSame( \array_values( $indexed ), $links );
		$this->assertSame( $slugs, \array_column( $links, 'slug' ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function zone( string $zoneSlug ) :string {
				return '/admin/zones/'.$zoneSlug;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->comps = (object)[
			'zones' => new class {
				public function getZones() :array {
					return [
						'secadmin' => new class {
							public static function Slug() :string {
								return 'secadmin';
							}

							public function title() :string {
								return 'Security Admin';
							}

							public function icon() :string {
								return 'shield-fill';
							}
						},
						'firewall' => new class {
							public static function Slug() :string {
								return 'firewall';
							}

							public function title() :string {
								return 'Firewall';
							}

							public function icon() :string {
								return 'shield-shaded';
							}
						},
					];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

