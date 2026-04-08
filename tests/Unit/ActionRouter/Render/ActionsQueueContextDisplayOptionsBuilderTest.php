<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueContextDisplayOptionsBuilder,
	ActionsQueueScanResultsOptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ActionsQueueContextDisplayOptionsBuilderTest extends BaseUnitTest {

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

	public function test_build_for_direct_table_group_emits_switches_and_submit_action() :void {
		$builder = new ActionsQueueContextDisplayOptionsBuilder(
			new class extends ActionsQueueScanResultsOptions {
				public function storedOptions() :array {
					return $this->normalize( [
						'include_repaired' => true,
					] );
				}
			}
		);

		$displayOptions = $builder->buildForGroup(
			'wordpress',
			'direct_table',
			[
				'display_context' => 'actions_queue',
			]
		);

		$this->assertSame( 'Display Results', $displayOptions[ 'title' ] ?? '' );
		$this->assertCount( 3, $displayOptions[ 'controls' ] ?? [] );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => true,
				'include_deleted'  => false,
			],
			\array_column( $displayOptions[ 'controls' ], 'checked', 'name' )
		);
		$actionData = \json_decode( (string)( $displayOptions[ 'action_json' ] ?? '' ), true );
		$this->assertIsArray( $actionData );
		$this->assertSame( 'scan_results_display_form_submit', $actionData[ 'ex' ] ?? '' );
	}

	public function test_build_for_ignored_direct_table_group_forces_ignored_switch_but_preserves_other_flags() :void {
		$builder = new ActionsQueueContextDisplayOptionsBuilder(
			new class extends ActionsQueueScanResultsOptions {
				public function storedOptions() :array {
					return $this->normalize( [
						'include_repaired' => true,
						'include_deleted'  => true,
					] );
				}
			}
		);

		$displayOptions = $builder->buildForGroup(
			'plugins',
			'direct_table',
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored'  => true,
					'include_repaired' => true,
					'include_deleted'  => true,
					'ignored_only'     => true,
				],
			]
		);

		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => true,
			],
			\array_combine(
				\array_column( $displayOptions[ 'controls' ], 'name' ),
				\array_map( static fn( array $control ) :bool => (bool)( $control[ 'checked' ] ?? false ), $displayOptions[ 'controls' ] )
			)
		);
		$this->assertTrue( (bool)( $displayOptions[ 'controls' ][ 0 ][ 'disabled' ] ?? false ) );
	}

	public function test_build_for_non_scan_group_returns_empty_contract() :void {
		$this->assertSame(
			[],
			( new ActionsQueueContextDisplayOptionsBuilder() )->buildForGroup(
				'maintenance',
				'maintenance',
				[]
			)
		);
	}
}
