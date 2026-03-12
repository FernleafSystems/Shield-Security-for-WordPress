<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansVulnerabilitiesBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ScansVulnerabilitiesBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_build_dedupes_distinct_assets_across_vulnerable_and_abandoned_sections() :void {
		$builder = new ScansVulnerabilitiesBuilderTestDouble(
			[
				[
					'key'         => 'vulnerability-plugin-one',
					'asset_key'   => 'plugin-one',
					'label'       => 'Plugin One',
					'description' => 'Two known vulnerabilities need review.',
					'count'       => 2,
					'severity'    => 'critical',
					'actions'     => [],
				],
			],
			[
				[
					'key'         => 'abandoned-plugin-one',
					'asset_key'   => 'plugin-one',
					'label'       => 'Plugin One',
					'description' => 'This asset appears to be abandoned.',
					'count'       => 1,
					'severity'    => 'warning',
					'actions'     => [],
				],
				[
					'key'         => 'abandoned-theme-two',
					'asset_key'   => 'theme-two',
					'label'       => 'Theme Two',
					'description' => 'This asset appears to be abandoned.',
					'count'       => 1,
					'severity'    => 'warning',
					'actions'     => [],
				],
			]
		);

		$payload = $builder->build();

		$this->assertSame( 2, $payload[ 'count' ] );
		$this->assertSame( 'critical', $payload[ 'status' ] );
		$this->assertCount( 1, $payload[ 'sections' ][ 'vulnerable' ][ 'items' ] );
		$this->assertCount( 2, $payload[ 'sections' ][ 'abandoned' ][ 'items' ] );
	}

	public function test_build_uses_warning_status_when_only_abandoned_assets_exist() :void {
		$builder = new ScansVulnerabilitiesBuilderTestDouble(
			[],
			[
				[
					'key'         => 'abandoned-plugin-one',
					'asset_key'   => 'plugin-one',
					'label'       => 'Plugin One',
					'description' => 'This asset appears to be abandoned.',
					'count'       => 1,
					'severity'    => 'warning',
					'actions'     => [],
				],
			]
		);

		$payload = $builder->build();

		$this->assertSame( 1, $payload[ 'count' ] );
		$this->assertSame( 'warning', $payload[ 'status' ] );
		$this->assertSame( [], $payload[ 'sections' ][ 'vulnerable' ][ 'items' ] );
		$this->assertCount( 1, $payload[ 'sections' ][ 'abandoned' ][ 'items' ] );
	}
}

class ScansVulnerabilitiesBuilderTestDouble extends ScansVulnerabilitiesBuilder {

	private array $vulnerableItems;
	private array $abandonedItems;

	public function __construct( array $vulnerableItems, array $abandonedItems ) {
		$this->vulnerableItems = $vulnerableItems;
		$this->abandonedItems = $abandonedItems;
	}

	protected function buildVulnerableItems() :array {
		return $this->vulnerableItems;
	}

	protected function buildAbandonedItems() :array {
		return $this->abandonedItems;
	}
}
