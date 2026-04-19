<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\BuildInfoHeadlineContract;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildInfoHeadlineContractTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_build_uses_live_overview_attention_posture_and_scan_freshness() :void {
		$builder = new class extends BuildInfoHeadlineContract {
			protected function buildOverviewQuery() :array {
				return [
					'attention_summary' => [
						'total'    => 2,
						'severity' => 'critical',
					],
					'posture'           => [
						'severity'   => 'warning',
						'percentage' => 82,
						'totals'     => [ 'letter_score' => 'B' ],
					],
					'scans'             => [
						'latest_completed_at' => [ 1710104400 ],
						'is_running'          => false,
						'enqueued_count'      => 0,
					],
				];
			}

			protected function formatTimestampDiff( int $timestamp ) :string {
				return '3 hours ago';
			}
		};

		$headline = $builder->build();

		$this->assertSame( '2 issues need attention', $headline[ 'summary' ][ 'title' ] );
		$this->assertSame( 'Current alert status across your site.', $headline[ 'summary' ][ 'subtitle' ] );
		$this->assertSame( '2 issues need attention', $headline[ 'cards' ][ 0 ][ 'value' ] );
		$this->assertSame( '82% configured', $headline[ 'cards' ][ 1 ][ 'value' ] );
		$this->assertSame( 'Current posture grade: B', $headline[ 'cards' ][ 1 ][ 'meta' ] );
		$this->assertSame( 'Last scan: 3 hours ago', $headline[ 'cards' ][ 2 ][ 'value' ] );
	}

	public function test_build_normalizes_all_clear_and_running_scan_states() :void {
		$builder = new class extends BuildInfoHeadlineContract {
			protected function buildOverviewQuery() :array {
				return [
					'attention_summary' => [
						'total'    => 0,
						'severity' => 'unknown',
					],
					'posture'           => [
						'severity'   => 'unknown',
						'percentage' => 100,
						'totals'     => [ 'letter_score' => 'A' ],
					],
					'scans'             => [
						'latest_completed_at' => [ 0 ],
						'is_running'          => true,
						'enqueued_count'      => 2,
					],
				];
			}
		};

		$headline = $builder->build();

		$this->assertSame( 'All clear right now', $headline[ 'summary' ][ 'title' ] );
		$this->assertSame( 'All clear', $headline[ 'cards' ][ 0 ][ 'value' ] );
		$this->assertSame( '100% configured', $headline[ 'cards' ][ 1 ][ 'value' ] );
		$this->assertSame( 'Scans running', $headline[ 'cards' ][ 2 ][ 'value' ] );
		$this->assertSame( '2 scan tasks queued', $headline[ 'cards' ][ 2 ][ 'meta' ] );
	}
}
