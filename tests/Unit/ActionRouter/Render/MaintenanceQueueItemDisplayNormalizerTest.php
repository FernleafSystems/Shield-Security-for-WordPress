<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\DetailExpansionType;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\MaintenanceQueueItemDisplayNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenanceAssetFixtures,
	ServicesState
};

class MaintenanceQueueItemDisplayNormalizerTest extends BaseUnitTest {

	use MaintenanceAssetFixtures;

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
					'akismet/akismet.php' => $this->buildMaintenancePluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
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
		$this->assertSame( DetailExpansionType::SIMPLE_TABLE, $item[ 'expansion' ][ 'type' ] ?? '' );
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
					'twentytwentyfive' => $this->buildMaintenanceThemeVo( 'twentytwentyfive', 'Twenty Twenty-Five', '1.1' ),
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
					'hello-dolly/hello.php' => $this->buildMaintenancePluginVo( 'hello-dolly/hello.php', 'Hello Dolly', '1.7.2' ),
					'akismet/akismet.php'   => $this->buildMaintenancePluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
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
					'child-theme'    => $this->buildMaintenanceThemeVo( 'child-theme', 'Child Theme', '1.2.0' ),
					'parent-theme'   => $this->buildMaintenanceThemeVo( 'parent-theme', 'Parent Theme', '2.5.0' ),
					'inactive-theme' => $this->buildMaintenanceThemeVo( 'inactive-theme', 'Inactive Theme', '3.0.1' ),
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
			'plugins' => [],
			'themes'  => [],
		], $fixture );

		ServicesState::installItems(
			$this->buildMaintenanceAssetServiceItems( $fixture[ 'plugins' ], $fixture[ 'themes' ] )
		);
	}
}
