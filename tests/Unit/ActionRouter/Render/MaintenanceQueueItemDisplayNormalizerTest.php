<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	DetailExpansionType,
	MaintenanceQueueItemDisplayNormalizer
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenanceAssetFixtures,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Request,
	Users
};

class MaintenanceQueueItemDisplayNormalizerTest extends BaseUnitTest {

	use MaintenanceAssetFixtures;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pairs = [];
				foreach ( $params as $key => $value ) {
					$pairs[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pairs );
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
		ServicesState::mergeItems( [
			'service_request' => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_plugin_update_rows_get_eager_simple_table_expansion_with_upgrade_and_ignore_actions() :void {
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

		$this->assertNotEmpty( $item[ 'cta' ][ 'label' ] ?? '' );
		$this->assertSame( 'maintenance-expand-wp_plugins_updates', $item[ 'expansion' ][ 'id' ] ?? '' );
		$this->assertSame( DetailExpansionType::SIMPLE_TABLE, $item[ 'expansion' ][ 'type' ] ?? '' );
		$this->assertSame( 'Akismet Anti-Spam', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'Current: 5.3.0 | Available: 5.4.0', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'context' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'identifier' ] ?? '' );
		$this->assertSame(
			'/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
			$item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? ''
		);
		$this->assertNotEmpty( $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertFalse( (bool)( $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'is_ignored' ] ?? true ) );
		$this->assertSame( '', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'ignored_label' ] ?? 'unexpected' );
		$this->assertSame( 'bi bi-eye-slash-fill', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'secondary_actions' ][ 0 ][ 'icon' ] ?? '' );
		$this->assertSame(
			'maintenance_item_ignore',
			$item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'secondary_actions' ][ 0 ][ 'ajax_action' ][ 'ex' ] ?? ''
		);
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
		$this->assertNotEmpty( $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertSame( '/wp-admin/update-core.php', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? '' );
	}

	public function test_inactive_plugin_rows_link_to_filtered_plugins_screen_and_not_activation() :void {
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

		$this->assertNotEmpty( $item[ 'cta' ][ 'label' ] ?? '' );
		$this->assertCount( 1, $item[ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertSame( 'Hello Dolly', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'Version: 1.7.2', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'context' ] ?? '' );
		$this->assertNotEmpty( $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'label' ] ?? '' );
		$this->assertNotEmpty( $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'tooltip' ] ?? '' );
		$this->assertTrue( (bool)( $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'is_icon_only' ] ?? false ) );
		$this->assertSame( '_blank', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'target' ] ?? '' );
		$this->assertSame(
			'/wp-admin/plugins.php?s=hello-dolly%2Fhello.php',
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

		$this->assertNotEmpty( $item[ 'cta' ][ 'label' ] ?? '' );
		$this->assertCount( 1, $item[ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertSame( 'Inactive Theme', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'inactive-theme', $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'identifier' ] ?? '' );
		$this->assertSame( [], $item[ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ] ?? [ 'unexpected' ] );
	}

	public function test_normalize_all_sorts_ignored_rows_after_active_rows() :void {
		$this->installServices( [
			'plugins' => [
				'plugins' => [
					'akismet/akismet.php'     => [],
					'hello-dolly/hello.php'   => [],
				],
				'updates' => [
					'akismet/akismet.php'   => [ 'new_version' => '5.4.0' ],
					'hello-dolly/hello.php' => [ 'new_version' => '1.8.0' ],
				],
				'plugin_vos' => [
					'akismet/akismet.php'   => $this->buildMaintenancePluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
					'hello-dolly/hello.php' => $this->buildMaintenancePluginVo( 'hello-dolly/hello.php', 'Hello Dolly', '1.7.2' ),
				],
				'upgrade_urls' => [
					'akismet/akismet.php'   => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
					'hello-dolly/hello.php' => '/wp-admin/update.php?action=upgrade-plugin&plugin=hello-dolly/hello.php',
				],
			],
		] );

		$items = ( new MaintenanceQueueItemDisplayNormalizerTestDouble( [
			'wp_plugins_updates' => [
				'key'                 => 'wp_plugins_updates',
				'label'               => 'Plugins With Updates',
				'description'         => '1 plugin update available. 1 item is currently ignored.',
				'count'               => 1,
				'ignored_count'       => 1,
				'severity'            => 'warning',
				'href'                => '/wp-admin/update-core.php',
				'action'              => 'Update',
				'target'              => '',
				'supports_sub_items'  => true,
				'active_identifiers'  => [ 'akismet/akismet.php' ],
				'ignored_identifiers' => [ 'hello-dolly/hello.php' ],
			],
		] ) )->normalizeAll( [
			[
				'key'         => 'wp_plugins_updates',
				'zone'        => 'maintenance',
				'label'       => 'Plugins With Updates',
				'count'       => 1,
				'severity'    => 'warning',
				'description' => 'Plugins need updates.',
				'href'        => '/wp-admin/update-core.php',
				'action'      => 'Update',
				'target'      => '',
			],
		] );

		$this->assertSame( [ 'Akismet Anti-Spam', 'Hello Dolly' ], \array_column( $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ], 'title' ) );
		$this->assertFalse( (bool)( $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'is_ignored' ] ?? true ) );
		$this->assertTrue( (bool)( $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 1 ][ 'is_ignored' ] ?? false ) );
		$this->assertNotEmpty( $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 1 ][ 'ignored_label' ] ?? '' );
		$this->assertSame( 'bi bi-eye-fill', $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 1 ][ 'secondary_actions' ][ 0 ][ 'icon' ] ?? '' );
		$this->assertSame(
			'maintenance_item_unignore',
			$items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 1 ][ 'secondary_actions' ][ 0 ][ 'ajax_action' ][ 'ex' ] ?? ''
		);
	}

	public function test_normalize_all_appends_fully_ignored_singleton_item_with_unignore_action() :void {
		$items = ( new MaintenanceQueueItemDisplayNormalizerTestDouble( [
			'system_php_version' => [
				'key'                 => 'system_php_version',
				'label'               => 'PHP Version',
				'description'         => 'This maintenance item is currently ignored.',
				'count'               => 0,
				'ignored_count'       => 1,
				'severity'            => 'good',
				'href'                => '/wp-admin/update-core.php',
				'action'              => 'Open',
				'target'              => '',
				'supports_sub_items'  => false,
				'active_identifiers'  => [],
				'ignored_identifiers' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			],
		] ) )->normalizeAll( [] );

		$this->assertCount( 1, $items );
		$this->assertSame( 'system_php_version', $items[ 0 ][ 'key' ] ?? '' );
		$this->assertSame( 'good', $items[ 0 ][ 'severity' ] ?? '' );
		$this->assertSame( [], $items[ 0 ][ 'expansion' ] ?? [] );
		$this->assertSame( 'bi bi-eye-fill', $items[ 0 ][ 'toggle_action' ][ 'icon' ] ?? '' );
		$this->assertSame( 'maintenance_item_unignore', $items[ 0 ][ 'toggle_action' ][ 'ajax_action' ][ 'ex' ] ?? '' );
	}

	public function test_normalize_all_appends_fully_ignored_sub_item_check_with_ignored_rows() :void {
		$this->installServices( [
			'plugins' => [
				'plugins' => [
					'akismet/akismet.php'     => [],
					'hello-dolly/hello.php'   => [],
				],
				'updates' => [
					'akismet/akismet.php'   => [ 'new_version' => '5.4.0' ],
					'hello-dolly/hello.php' => [ 'new_version' => '1.8.0' ],
				],
				'plugin_vos' => [
					'akismet/akismet.php'   => $this->buildMaintenancePluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
					'hello-dolly/hello.php' => $this->buildMaintenancePluginVo( 'hello-dolly/hello.php', 'Hello Dolly', '1.7.2' ),
				],
				'upgrade_urls' => [
					'akismet/akismet.php'   => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
					'hello-dolly/hello.php' => '/wp-admin/update.php?action=upgrade-plugin&plugin=hello-dolly/hello.php',
				],
			],
		] );

		$items = ( new MaintenanceQueueItemDisplayNormalizerTestDouble( [
			'wp_plugins_updates' => [
				'key'                 => 'wp_plugins_updates',
				'label'               => 'Plugins With Updates',
				'description'         => '2 items are currently ignored.',
				'count'               => 0,
				'ignored_count'       => 2,
				'severity'            => 'good',
				'href'                => '/wp-admin/update-core.php',
				'action'              => 'Update',
				'target'              => '',
				'supports_sub_items'  => true,
				'active_identifiers'  => [],
				'ignored_identifiers' => [
					'akismet/akismet.php',
					'hello-dolly/hello.php',
				],
			],
		] ) )->normalizeAll( [] );

		$this->assertCount( 1, $items );
		$this->assertSame( 'good', $items[ 0 ][ 'severity' ] ?? '' );
		$this->assertSame( [], $items[ 0 ][ 'toggle_action' ] ?? [] );
		$this->assertSame( DetailExpansionType::SIMPLE_TABLE, $items[ 0 ][ 'expansion' ][ 'type' ] ?? '' );
		$this->assertCount( 2, $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertTrue( (bool)( $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'is_ignored' ] ?? false ) );
		$this->assertNotEmpty( $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'ignored_label' ] ?? '' );
		$this->assertSame( 'bi bi-eye-fill', $items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'secondary_actions' ][ 0 ][ 'icon' ] ?? '' );
		$this->assertSame(
			'maintenance_item_unignore',
			$items[ 0 ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'secondary_actions' ][ 0 ][ 'ajax_action' ][ 'ex' ] ?? ''
		);
	}

	private function installServices( array $fixture = [] ) :void {
		$fixture = \array_merge( [
			'plugins' => [],
			'themes'  => [],
		], $fixture );

		ServicesState::installItems( \array_merge(
			$this->buildMaintenanceAssetServiceItems( $fixture[ 'plugins' ], $fixture[ 'themes' ] ),
			[
				'service_request' => new class extends Request {
					public function ip() :string {
						return '127.0.0.1';
					}

					public function ts( bool $update = true ) :int {
						return 1700000000;
					}
				},
				'service_wpusers' => new class extends Users {
					public function getCurrentWpUserId() {
						return 0;
					}
				},
			]
		) );
	}
}

class MaintenanceQueueItemDisplayNormalizerTestDouble extends MaintenanceQueueItemDisplayNormalizer {

	private array $states;

	public function __construct( array $states ) {
		$this->states = $states;
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new class( $this->states ) extends MaintenanceIssueStateProvider {
			private array $states;

			public function __construct( array $states ) {
				$this->states = $states;
			}

			public function buildStates() :array {
				return $this->states;
			}
		};
	}
}
