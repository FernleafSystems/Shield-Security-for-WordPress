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
						'zones'      => [
							'total'    => 6,
							'critical' => 1,
							'warning'  => 2,
							'good'     => 3,
						],
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

		$this->assertSame( 'attention', $headline[ 'summary' ][ 'state' ] );
		$this->assertSame( 2, $headline[ 'summary' ][ 'total_issues' ] );
		$this->assertNotSame( '', \trim( (string)( $headline[ 'summary' ][ 'title' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $headline[ 'summary' ][ 'subtitle' ] ?? '' ) ) );
		$this->assertSame( [ 'attention', 'coverage', 'scans' ], \array_column( $headline[ 'cards' ], 'key' ) );

		$attentionCard = $headline[ 'cards' ][ 0 ];
		$this->assertSame( 'attention', $attentionCard[ 'state' ] );
		$this->assertSame( 'critical', $attentionCard[ 'severity' ] );
		$this->assertSame( 2, $attentionCard[ 'total_issues' ] );
		$this->assertSame( $headline[ 'summary' ][ 'title' ], $attentionCard[ 'value' ] );
		$this->assertNotSame( '', \trim( (string)( $attentionCard[ 'label' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $attentionCard[ 'meta' ] ?? '' ) ) );

		$coverageCard = $headline[ 'cards' ][ 1 ];
		$this->assertSame( 'warning', $coverageCard[ 'severity' ] );
		$this->assertSame( 82, $coverageCard[ 'percentage' ] );
		$this->assertSame( [
			'total'    => 6,
			'good'     => 3,
			'warning'  => 2,
			'critical' => 1,
		], $coverageCard[ 'zones' ] );
		$this->assertNotSame( '', \trim( (string)( $coverageCard[ 'label' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $coverageCard[ 'value' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $coverageCard[ 'meta' ] ?? '' ) ) );

		$scansCard = $headline[ 'cards' ][ 2 ];
		$this->assertSame( 'completed', $scansCard[ 'state' ] );
		$this->assertSame( 0, $scansCard[ 'enqueued_count' ] );
		$this->assertSame( 1710104400, $scansCard[ 'latest_completed_at' ] );
		$this->assertNotSame( '', \trim( (string)( $scansCard[ 'label' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $scansCard[ 'value' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $scansCard[ 'meta' ] ?? '' ) ) );
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
						'zones'      => [
							'total'    => 5,
							'critical' => 0,
							'warning'  => 0,
							'good'     => 5,
						],
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

		$this->assertSame( 'all_clear', $headline[ 'summary' ][ 'state' ] );
		$this->assertSame( 0, $headline[ 'summary' ][ 'total_issues' ] );
		$this->assertNotSame( '', \trim( (string)( $headline[ 'summary' ][ 'title' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $headline[ 'summary' ][ 'subtitle' ] ?? '' ) ) );
		$this->assertSame( [ 'attention', 'coverage', 'scans' ], \array_column( $headline[ 'cards' ], 'key' ) );

		$attentionCard = $headline[ 'cards' ][ 0 ];
		$this->assertSame( 'all_clear', $attentionCard[ 'state' ] );
		$this->assertSame( 'good', $attentionCard[ 'severity' ] );
		$this->assertSame( 0, $attentionCard[ 'total_issues' ] );
		$this->assertNotSame( '', \trim( (string)( $attentionCard[ 'label' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $attentionCard[ 'value' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $attentionCard[ 'meta' ] ?? '' ) ) );

		$coverageCard = $headline[ 'cards' ][ 1 ];
		$this->assertSame( 'good', $coverageCard[ 'severity' ] );
		$this->assertSame( 100, $coverageCard[ 'percentage' ] );
		$this->assertSame( [
			'total'    => 5,
			'good'     => 5,
			'warning'  => 0,
			'critical' => 0,
		], $coverageCard[ 'zones' ] );
		$this->assertNotSame( '', \trim( (string)( $coverageCard[ 'label' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $coverageCard[ 'value' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $coverageCard[ 'meta' ] ?? '' ) ) );

		$scansCard = $headline[ 'cards' ][ 2 ];
		$this->assertSame( 'running', $scansCard[ 'state' ] );
		$this->assertSame( 2, $scansCard[ 'enqueued_count' ] );
		$this->assertSame( 0, $scansCard[ 'latest_completed_at' ] );
		$this->assertNotSame( '', \trim( (string)( $scansCard[ 'label' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $scansCard[ 'value' ] ?? '' ) ) );
		$this->assertNotSame( '', \trim( (string)( $scansCard[ 'meta' ] ?? '' ) ) );
	}
}
