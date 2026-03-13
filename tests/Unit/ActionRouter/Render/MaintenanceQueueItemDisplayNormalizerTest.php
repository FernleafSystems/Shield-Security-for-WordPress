<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\MaintenanceQueueItemDisplayNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class MaintenanceQueueItemDisplayNormalizerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_plugin_update_rows_get_eager_simple_table_expansion_with_upgrade_actions() :void {
		$this->installServices( [
			'plugins' => [
				'plugins' => [
					'akismet/akismet.php' => [],
				],
				'updates' => [
					'akismet/akismet.php' => [ 'new_version' => '5.4.0' ],
				],
				'plugin_vos' => [
					'akismet/akismet.php' => $this->buildPluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
				],
				'upgrade_urls' => [
					'akismet/akismet.php' => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
				],
			],
		] );

		$item = ( new MaintenanceQueueItemDisplayNormalizer() )->normalize( [
			'key'         => 'wp_plugins_updates',
			'zone'        => 'maintenance',
			'label'       => 'Plugins With Updates',
			'count'       => 1,
			'severity'    => 'warning',
			'description' => 'Plugins need updates.',
			'href'        => '/wp-admin/update-core.php',
			'action'      => 'Update',
			'target'      => '',
		] );

		$this->assertSame( 'Update', $item[ 'cta' ][ 'label' ] ?? '' );
		$this->assertSame( 'maintenance-expand-wp_plugins_updates', $item[ 'expansion' ][ 'id' ] ?? '' );
		$this->assertSame( 'simple_table', $item[ 'expansion' ][ 'type' ] ?? '' );
		$this->assertSame( 'Akismet Anti-Spam', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'Current: 5.3.0 | Available: 5.4.0', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'context' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'identifier' ] ?? '' );
		$this->assertSame(
			'/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
			$item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? ''
		);
		$this->assertSame( 'Update', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
	}

	public function test_theme_update_rows_get_eager_simple_table_expansion_with_existing_updates_screen_action() :void {
		$this->installServices( [
			'themes' => [
				'themes' => [
					'twentytwentyfive' => [],
				],
				'updates' => [
					'twentytwentyfive' => [ 'new_version' => '1.2' ],
				],
				'theme_vos' => [
					'twentytwentyfive' => $this->buildThemeVo( 'twentytwentyfive', 'Twenty Twenty-Five', '1.1' ),
				],
			],
		] );

		$item = ( new MaintenanceQueueItemDisplayNormalizer() )->normalize( [
			'key'         => 'wp_themes_updates',
			'zone'        => 'maintenance',
			'label'       => 'Themes With Updates',
			'count'       => 1,
			'severity'    => 'warning',
			'description' => 'Themes need updates.',
			'href'        => '/wp-admin/update-core.php',
			'action'      => 'Update',
			'target'      => '',
		] );

		$this->assertSame( 'maintenance-expand-wp_themes_updates', $item[ 'expansion' ][ 'id' ] ?? '' );
		$this->assertSame( 'Twenty Twenty-Five', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'Current: 1.1 | Available: 1.2', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'context' ] ?? '' );
		$this->assertSame( 'Open updates', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertSame( '/wp-admin/update-core.php', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? '' );
	}

	public function test_inactive_plugin_rows_are_built_from_installed_minus_active_plugins() :void {
		$this->installServices( [
			'plugins' => [
				'plugins' => [
					'hello-dolly/hello.php' => [],
					'akismet/akismet.php'   => [],
				],
				'active' => [
					'akismet/akismet.php',
				],
				'plugin_vos' => [
					'hello-dolly/hello.php' => $this->buildPluginVo( 'hello-dolly/hello.php', 'Hello Dolly', '1.7.2' ),
					'akismet/akismet.php'   => $this->buildPluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
				],
				'activate_urls' => [
					'hello-dolly/hello.php' => '/wp-admin/plugins.php?action=activate&plugin=hello-dolly/hello.php',
				],
			],
		] );

		$item = ( new MaintenanceQueueItemDisplayNormalizer() )->normalize( [
			'key'         => 'wp_plugins_inactive',
			'zone'        => 'maintenance',
			'label'       => 'Inactive Plugins',
			'count'       => 1,
			'severity'    => 'warning',
			'description' => 'Inactive plugins found.',
			'href'        => '/wp-admin/plugins.php',
			'action'      => 'Open',
			'target'      => '',
		] );

		$this->assertSame( 'Go to plugins', $item[ 'cta' ][ 'label' ] ?? '' );
		$this->assertCount( 1, $item[ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertSame( 'Hello Dolly', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'Version: 1.7.2', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'context' ] ?? '' );
		$this->assertSame( 'Activate', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertSame(
			'/wp-admin/plugins.php?action=activate&plugin=hello-dolly/hello.php',
			$item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? ''
		);
	}

	public function test_inactive_theme_rows_exclude_active_child_theme_and_parent_theme() :void {
		$this->installServices( [
			'themes' => [
				'themes' => [
					'child-theme'    => [],
					'parent-theme'   => [],
					'inactive-theme' => [],
				],
				'theme_vos' => [
					'child-theme'    => $this->buildThemeVo( 'child-theme', 'Child Theme', '1.2.0' ),
					'parent-theme'   => $this->buildThemeVo( 'parent-theme', 'Parent Theme', '2.5.0' ),
					'inactive-theme' => $this->buildThemeVo( 'inactive-theme', 'Inactive Theme', '3.0.1' ),
				],
				'current'        => 'child-theme',
				'current_parent' => 'parent-theme',
			],
		] );

		$item = ( new MaintenanceQueueItemDisplayNormalizer() )->normalize( [
			'key'         => 'wp_themes_inactive',
			'zone'        => 'maintenance',
			'label'       => 'Inactive Themes',
			'count'       => 1,
			'severity'    => 'warning',
			'description' => 'Inactive themes found.',
			'href'        => '/wp-admin/themes.php',
			'action'      => 'Open',
			'target'      => '',
		] );

		$this->assertSame( 'Go to themes', $item[ 'cta' ][ 'label' ] ?? '' );
		$this->assertCount( 1, $item[ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertSame( 'Inactive Theme', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'inactive-theme', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'identifier' ] ?? '' );
		$this->assertSame( 'Open themes', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertSame( '/wp-admin/themes.php', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? '' );
	}

	private function installServices( array $fixture = [] ) :void {
		$fixture = \array_merge( [
			'plugins' => [
				'updates'       => [],
				'plugins'       => [],
				'active'        => [],
				'plugin_vos'    => [],
				'activate_urls' => [],
				'upgrade_urls'  => [],
			],
			'themes'  => [
				'updates'        => [],
				'themes'         => [],
				'theme_vos'      => [],
				'current'        => '',
				'current_parent' => '',
			],
		], $fixture );

		ServicesState::installItems( [
			'service_wpgeneral' => new class extends General {
				public function getAdminUrl_Updates( bool $bWpmsOnly = false ) :string {
					return '/wp-admin/update-core.php';
				}

				public function getAdminUrl_Themes( bool $wpmsOnly = false ) :string {
					return '/wp-admin/themes.php';
				}
			},
			'service_wpplugins' => new class( $fixture[ 'plugins' ] ) extends Plugins {
				private array $fixture;

				public function __construct( array $fixture ) {
					$this->fixture = $fixture;
				}

				public function getUpdates( $bForceUpdateCheck = false ) {
					return $this->fixture[ 'updates' ];
				}

				public function getPlugins() :array {
					return $this->fixture[ 'plugins' ];
				}

				public function getActivePlugins() :array {
					return $this->fixture[ 'active' ];
				}

				public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
					return $this->fixture[ 'plugin_vos' ][ $file ] ?? null;
				}

				public function getUrl_Activate( $file ) :string {
					return (string)( $this->fixture[ 'activate_urls' ][ $file ] ?? '' );
				}

				public function getUrl_Upgrade( $file ) :string {
					return (string)( $this->fixture[ 'upgrade_urls' ][ $file ] ?? '' );
				}
			},
			'service_wpthemes' => new class( $fixture[ 'themes' ] ) extends Themes {
				private array $fixture;

				public function __construct( array $fixture ) {
					$this->fixture = $fixture;
				}

				public function getUpdates( $forceCheck = false ) {
					return $this->fixture[ 'updates' ];
				}

				public function getThemes() :array {
					return $this->fixture[ 'themes' ];
				}

				public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
					return $this->fixture[ 'theme_vos' ][ $stylesheet ] ?? null;
				}

				public function getCurrent() {
					return new class( (string)( $this->fixture[ 'current' ] ?? '' ) ) {
						private string $stylesheet;

						public function __construct( string $stylesheet ) {
							$this->stylesheet = $stylesheet;
						}

						public function get_stylesheet() :string {
							return $this->stylesheet;
						}
					};
				}

				public function getCurrentParent() {
					$stylesheet = (string)( $this->fixture[ 'current_parent' ] ?? '' );
					if ( $stylesheet === '' ) {
						return null;
					}

					return new class( $stylesheet ) {
						private string $stylesheet;

						public function __construct( string $stylesheet ) {
							$this->stylesheet = $stylesheet;
						}

						public function get_stylesheet() :string {
							return $this->stylesheet;
						}
					};
				}
			},
		] );
	}

	private function buildPluginVo( string $file, string $title, string $version ) :WpPluginVo {
		return new class( $file, $title, $version ) extends WpPluginVo {
			public string $file;
			public string $Title;
			public string $Name;
			public string $Version;

			public function __construct( string $file, string $title, string $version ) {
				$this->file = $file;
				$this->Title = $title;
				$this->Name = $title;
				$this->Version = $version;
			}
		};
	}

	private function buildThemeVo( string $stylesheet, string $name, string $version ) :WpThemeVo {
		return new class( $stylesheet, $name, $version ) extends WpThemeVo {
			public string $stylesheet;
			public string $Name;
			public string $Version;

			public function __construct( string $stylesheet, string $name, string $version ) {
				$this->stylesheet = $stylesheet;
				$this->Name = $name;
				$this->Version = $version;
			}
		};
	}
}
