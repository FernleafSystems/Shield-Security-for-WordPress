<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Abilities;

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
		$this->assertTrue( $definitions[ 0 ][ 'args' ][ 'permission_callback' ]() );
	}
}

class PluginControllerInstallerTestHelper {

	public static function controller() :Controller {
		return PluginStore::$plugin->getController();
	}
}
