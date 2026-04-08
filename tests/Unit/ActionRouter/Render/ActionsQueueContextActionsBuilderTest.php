<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueContextActionsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ActionsQueueContextActionsBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
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
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
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

	public function test_build_for_active_plugin_direct_table_emits_ignore_all_action() :void {
		$actions = ( new ActionsQueueContextActionsBuilder() )->buildForGroup(
			'plugins',
			'Example Plugin',
			'direct_table',
			3,
			[
				'display_context' => 'actions_queue',
				'subject_type'    => 'plugin',
				'subject_id'      => 'example-plugin/example-plugin.php',
			]
		);

		$this->assertIgnoreAllAction( $actions, 'plugin', 'example-plugin/example-plugin.php' );
	}

	public function test_build_for_active_wordpress_direct_table_emits_ignore_all_action() :void {
		$actions = ( new ActionsQueueContextActionsBuilder() )->buildForGroup(
			'wordpress',
			'WordPress Files',
			'direct_table',
			2,
			[
				'display_context' => 'actions_queue',
			]
		);

		$this->assertIgnoreAllAction( $actions, 'wordpress', 'wordpress' );
	}

	public function test_build_for_active_malware_direct_table_emits_ignore_all_action() :void {
		$actions = ( new ActionsQueueContextActionsBuilder() )->buildForGroup(
			'malware',
			'Malware Detections',
			'direct_table',
			2,
			[
				'display_context' => 'actions_queue',
			]
		);

		$this->assertIgnoreAllAction( $actions, 'malware', 'malware' );
	}

	public function test_build_for_active_theme_direct_table_emits_ignore_all_action() :void {
		$actions = ( new ActionsQueueContextActionsBuilder() )->buildForGroup(
			'themes',
			'Example Theme',
			'direct_table',
			2,
			[
				'display_context' => 'actions_queue',
				'subject_type'    => 'theme',
				'subject_id'      => 'example-theme',
			]
		);

		$this->assertIgnoreAllAction( $actions, 'theme', 'example-theme' );
	}

	public function test_build_for_ignored_only_or_non_direct_groups_returns_no_actions() :void {
		$builder = new ActionsQueueContextActionsBuilder();

		$this->assertSame( [], $builder->buildForGroup(
			'wordpress',
			'WordPress Files',
			'direct_table',
			2,
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored' => true,
					'ignored_only'    => true,
				],
			]
		) );
		$this->assertSame( [], $builder->buildForGroup(
			'plugins',
			'Plugin Files',
			'asset_cards',
			2,
			[
				'display_context' => 'actions_queue',
			]
		) );
	}

	private function assertIgnoreAllAction( array $actions, string $expectedType, string $expectedFile ) :void {
		$this->assertCount( 1, $actions );
		$this->assertSame( 'Ignore All Results', $actions[ 0 ][ 'label' ] ?? '' );
		$this->assertSame( 'ajax', $actions[ 0 ][ 'kind' ] ?? '' );
		$this->assertSame( 'deactivate', $actions[ 0 ][ 'type' ] ?? '' );

		$actionData = \json_decode( (string)( $actions[ 0 ][ 'ajax_action_json' ] ?? '' ), true );
		$this->assertIsArray( $actionData );
		$this->assertSame( 'ignore_all', $actionData[ 'sub_action' ] ?? '' );
		$this->assertSame( $expectedType, $actionData[ 'type' ] ?? '' );
		$this->assertSame( $expectedFile, $actionData[ 'file' ] ?? '' );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$actionData[ 'results_display_options' ] ?? []
		);
	}
}
