<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Abilities;

if ( !\class_exists( 'WP_Error' ) ) {
	class ShieldWpErrorStub {
		private string $code;
		private string $message;
		private array $data;

		public function __construct( string $code = '', string $message = '', $data = [] ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = \is_array( $data ) ? $data : [];
		}

		public function get_error_code() :string {
			return $this->code;
		}

		public function get_error_message() :string {
			return $this->message;
		}

		public function get_error_data() :array {
			return $this->data;
		}
	}

	\class_alias( __NAMESPACE__.'\\ShieldWpErrorStub', 'WP_Error' );
}

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	McpTestControllerFactory,
	PluginControllerInstaller,
	PluginStore
};

class AbilityDefinitionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );

		McpTestControllerFactory::install( [
			'events'     => new class {
				public array $firedEvents = [];

				public function fireEvent( string $event, array $meta = [] ) :void {
					$this->firedEvents[] = [
						'event' => $event,
						'meta'  => $meta,
					];
				}
			},
			'scans'      => new class {
				public function getScanSlugs() :array {
					return [ 'afs', 'wpv', 'apc' ];
				}
			},
			'site_query' => new class {
				public array $scanFindingsCalls = [];

				public function overview() :array {
					return [ 'overview' => true ];
				}

				public function attention() :array {
					return [ 'attention' => true ];
				}

				public function recentActivity() :array {
					return [ 'recent' => true ];
				}

				public function scanFindings( array $scanSlugs = [], array $statesToInclude = [] ) :array {
					$this->scanFindingsCalls[] = [
						'scan_slugs' => $scanSlugs,
						'states'     => $statesToInclude,
					];
					return [ 'scan_findings' => true ];
				}
			},
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_returns_expected_ability_names_and_callbacks() :void {
		$definitions = ( new AbilityDefinitions() )->build();

		$this->assertSame( AbilityDefinitions::MCP_ABILITY_NAMES, \array_column( $definitions, 'name' ) );

		$overview = $definitions[ 0 ][ 'args' ][ 'execute_callback' ];
		$scanFindings = $definitions[ 3 ][ 'args' ][ 'execute_callback' ];

		$this->assertSame( [ 'overview' => true ], $overview() );
		$this->assertSame( [ 'scan_findings' => true ], $scanFindings( [
			'scan_slugs'        => [ 'wpv', '', 'apc' ],
			'filter_item_state' => [ 'is_vulnerable', '' ],
		] ) );
		$this->assertSame( [
			[
				'scan_slugs' => [ 'wpv', 'apc' ],
				'states'     => [ 'is_vulnerable' ],
			],
		], PluginControllerInstallerTestHelper::controller()->comps->site_query->scanFindingsCalls );
		$this->assertSame( [
			[
				'event' => 'mcp_ability_called',
				'meta'  => [
					'audit_params' => [
						'ability' => AbilityDefinitions::NAME_POSTURE_OVERVIEW,
						'status'  => 'success',
					],
				],
			],
			[
				'event' => 'mcp_ability_called',
				'meta'  => [
					'audit_params' => [
						'ability' => AbilityDefinitions::NAME_SCAN_FINDINGS,
						'status'  => 'success',
					],
				],
			],
		], PluginControllerInstallerTestHelper::controller()->comps->events->firedEvents );
	}

	public function test_scan_findings_callback_returns_wp_error_for_invalid_input() :void {
		McpTestControllerFactory::install( [
			'events'     => new class {
				public array $firedEvents = [];

				public function fireEvent( string $event, array $meta = [] ) :void {
					$this->firedEvents[] = [
						'event' => $event,
						'meta'  => $meta,
					];
				}
			},
			'scans'      => new class {
				public function getScanSlugs() :array {
					return [ 'afs', 'wpv', 'apc' ];
				}
			},
			'site_query' => new class {
				public function overview() :array {
					return [];
				}

				public function attention() :array {
					return [];
				}

				public function recentActivity() :array {
					return [];
				}

				public function scanFindings( array $scanSlugs = [], array $statesToInclude = [] ) :array {
					unset( $scanSlugs, $statesToInclude );
					throw new \InvalidArgumentException( 'Invalid scan item states provided.' );
				}
			},
		] );

		$definitions = ( new AbilityDefinitions() )->build();
		$result = $definitions[ 3 ][ 'args' ][ 'execute_callback' ]( [
			'filter_item_state' => [ 'bad-state' ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_mcp_invalid_input', $result->get_error_code() );
		$this->assertSame( [
			[
				'event' => 'mcp_ability_called',
				'meta'  => [
					'audit_params' => [
						'ability' => AbilityDefinitions::NAME_SCAN_FINDINGS,
						'status'  => 'invalid_input',
					],
				],
			],
		], PluginControllerInstallerTestHelper::controller()->comps->events->firedEvents );
	}

	public function test_generic_wp_error_results_are_audited_as_error() :void {
		McpTestControllerFactory::install( [
			'events'     => new class {
				public array $firedEvents = [];

				public function fireEvent( string $event, array $meta = [] ) :void {
					$this->firedEvents[] = [
						'event' => $event,
						'meta'  => $meta,
					];
				}
			},
			'scans'      => new class {
				public function getScanSlugs() :array {
					return [ 'afs', 'wpv', 'apc' ];
				}
			},
			'site_query' => new class {
				public function overview() :array {
					return [];
				}

				public function attention() :array {
					return [];
				}

				public function recentActivity() :array {
					return [];
				}

				public function scanFindings( array $scanSlugs = [], array $statesToInclude = [] ) {
					unset( $scanSlugs, $statesToInclude );
					return new \WP_Error( 'scan_failed', 'Scan failed.' );
				}
			},
		] );

		$definitions = ( new AbilityDefinitions() )->build();
		$result = $definitions[ 3 ][ 'args' ][ 'execute_callback' ]();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'scan_failed', $result->get_error_code() );
		$this->assertSame( [
			[
				'event' => 'mcp_ability_called',
				'meta'  => [
					'audit_params' => [
						'ability' => AbilityDefinitions::NAME_SCAN_FINDINGS,
						'status'  => 'error',
					],
				],
			],
		], PluginControllerInstallerTestHelper::controller()->comps->events->firedEvents );
	}
}

class PluginControllerInstallerTestHelper {

	public static function controller() :Controller {
		return PluginStore::$plugin->getController();
	}
}
