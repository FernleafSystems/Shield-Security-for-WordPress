<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\DataTables\LoadData\Reports;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForReports;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Reports\ReportsTableRequestNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ReportsTableRequestNormalizerTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_normalize_clamps_paging_and_rebuilds_canonical_contract() :void {
		$normalized = ( new ReportsTableRequestNormalizer() )->normalize( [
			'draw'    => '7',
			'start'   => -50,
			'length'  => 100000,
			'columns' => [
				[
					'data' => 'title',
				],
			],
			'order'   => [
				[
					'column' => 0,
					'dir'    => 'ASC',
				],
			],
			'search'  => [
				'value' => 'Alpha',
				'regex' => true,
			],
			'extra'   => 'drop-me',
		] );

		$reportsConfig = ( new ForReports() )->buildRaw();
		$this->assertSame( 7, $normalized[ 'draw' ] );
		$this->assertSame( 0, $normalized[ 'start' ] );
		$this->assertSame( 100, $normalized[ 'length' ] );
		$this->assertSame( [ 'value' => 'Alpha' ], $normalized[ 'search' ] );
		$this->assertSame( $reportsConfig[ 'columns' ], $normalized[ 'columns' ] );
		$this->assertSame( [
			[
				'column' => 3,
				'dir'    => 'ASC',
			],
		], $normalized[ 'order' ] );
		$this->assertArrayNotHasKey( 'extra', $normalized );
	}

	public function test_normalize_clamps_zero_length_to_minimum() :void {
		$normalized = ( new ReportsTableRequestNormalizer() )->normalize( [
			'length' => 0,
		] );

		$this->assertSame( 1, $normalized[ 'length' ] );
	}

	public function test_normalize_defaults_non_numeric_length_and_direction() :void {
		$normalized = ( new ReportsTableRequestNormalizer() )->normalize( [
			'length' => 'many',
			'order'  => [
				[
					'dir' => 'sideways',
				],
			],
		] );

		$this->assertSame( 25, $normalized[ 'length' ] );
		$this->assertSame( [
			[
				'column' => 3,
				'dir'    => 'DESC',
			],
		], $normalized[ 'order' ] );
	}
}
