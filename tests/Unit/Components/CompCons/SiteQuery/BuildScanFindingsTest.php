<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildScanFindings;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;

class BuildScanFindingsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private BuildScanFindingsTestDouble $builder;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new class extends Request {
				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
		] );
		$this->builder = new BuildScanFindingsTestDouble( true );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_filters_items_and_defaults_to_all_scan_slugs() :void {
		$query = $this->builder->build( [], [ 'is_vulnerable' ] );

		$this->assertSame( 1700000000, $query[ 'generated_at' ] );
		$this->assertTrue( $query[ 'is_available' ] );
		$this->assertSame( '', $query[ 'message' ] );
		$this->assertSame( [ 'afs', 'wpv' ], $query[ 'filters' ][ 'scan_slugs' ] );
		$this->assertSame( [ 'is_vulnerable' ], $query[ 'filters' ][ 'states' ] );

		$this->assertSame( 0, $query[ 'results' ][ 'afs' ][ 'total' ] );
		$this->assertSame( [], $query[ 'results' ][ 'afs' ][ 'items' ] );
		$this->assertSame( 1, $query[ 'results' ][ 'wpv' ][ 'total' ] );
		$this->assertSame( [
			'item_id'        => 'plugin-one',
			'states'         => [ 'is_vulnerable' ],
			'is_ignored'     => false,
		], $query[ 'results' ][ 'wpv' ][ 'items' ][ 0 ] );
		$this->assertSame( [
			'afs' => [ 'is_vulnerable' ],
			'wpv' => [ 'is_vulnerable' ],
		], $this->builder->statesRequestedByScanSlug );
	}

	public function test_build_normalizes_file_findings_to_minimal_contract() :void {
		$query = $this->builder->build( [ 'afs' ], [ 'is_checksumfail' ] );

		$this->assertSame( [
			'item_id'    => 'wp-admin/admin.php',
			'states'     => [ 'is_checksumfail' ],
			'is_ignored' => false,
			'scope'      => 'core',
		], $query[ 'results' ][ 'afs' ][ 'items' ][ 0 ] );
	}

	public function test_build_marks_findings_unavailable_while_scans_are_running() :void {
		$query = ( new BuildScanFindingsTestDouble( false ) )->build( [ 'wpv' ], [] );

		$this->assertFalse( $query[ 'is_available' ] );
		$this->assertSame( 'Results are unavailable while scans are currently running.', $query[ 'message' ] );
		$this->assertSame( [], $query[ 'results' ] );
	}

	public function test_build_rejects_invalid_supplied_scan_slugs() :void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid scan slugs provided.' );

		$this->builder->build( [ 'bad-scan' ], [] );
	}

	public function test_build_rejects_invalid_supplied_states() :void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid scan item states provided.' );

		$this->builder->build( [ 'wpv' ], [ 'bad-state' ] );
	}
}

class BuildScanFindingsTestDouble extends BuildScanFindings {

	private bool $available;

	public array $statesRequestedByScanSlug = [];

	public function __construct( bool $available ) {
		$this->available = $available;
	}

	protected function isAvailable() :bool {
		return $this->available;
	}

	protected function getScanSlugs() :array {
		return [ 'afs', 'wpv' ];
	}

	protected function getRawScanItems( string $scanSlug, array $statesToInclude = [] ) :array {
		$this->statesRequestedByScanSlug[ $scanSlug ] = $statesToInclude;
		return [
			'afs' => [
				[
					'item_id'    => 'wp-admin/admin.php',
					'ignored_at' => 0,
					'states'     => [ 'is_checksumfail' ],
					'scope'      => 'core',
				],
			],
			'wpv' => [
				[
					'item_id'    => 'plugin-one',
					'ignored_at' => 0,
					'states'     => [ 'is_vulnerable' ],
				],
				[
					'item_id'    => 'plugin-two',
					'ignored_at' => 0,
					'states'     => [ 'is_abandoned' ],
				],
			],
		][ $scanSlug ] ?? [];
	}
}
