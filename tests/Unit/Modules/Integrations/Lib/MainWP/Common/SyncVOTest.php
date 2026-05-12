<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SyncVOTest extends BaseUnitTest {

	public function test_mainwp_issues_summary_maps_canonical_severities_to_expected_button_classes() :void {
		$cases = [
			'good'     => [ 'total' => 0, 'button_class' => 'green', 'has_issues' => false ],
			'warning'  => [ 'total' => 4, 'button_class' => 'orange', 'has_issues' => true ],
			'critical' => [ 'total' => 2, 'button_class' => 'red', 'has_issues' => true ],
		];

		foreach ( $cases as $severity => $expected ) {
			$sync = ( new SyncVO() )->applyFromArray( [
				'overview' => [
					'attention_summary' => [
						'total'    => $expected[ 'total' ],
						'severity' => $severity,
					],
				],
			] );

			$this->assertTrue( $sync->hasMainwpAttentionSummary() );
			$this->assertSame( [
				'count'        => $expected[ 'total' ],
				'severity'     => $severity,
				'has_issues'   => $expected[ 'has_issues' ],
				'button_class' => $expected[ 'button_class' ],
			], $sync->mainwpIssuesSummary() );
		}
	}

	public function test_mainwp_issues_summary_defaults_inactive_or_invalid_payload_to_all_clear() :void {
		$sync = ( new SyncVO() )->applyFromArray( [
			'overview' => [
				'attention_summary' => [
					'total'    => -3,
					'severity' => 'unknown',
				],
			],
		] );

		$this->assertFalse( $sync->hasMainwpAttentionSummary() );

		$this->assertSame( [
			'count'        => 0,
			'severity'     => 'good',
			'has_issues'   => false,
			'button_class' => 'green',
		], $sync->mainwpIssuesSummary() );

		$this->assertSame( [
			'count'        => 0,
			'severity'     => 'good',
			'has_issues'   => false,
			'button_class' => 'green',
		], $sync->mainwpIssuesSummary( false ) );
	}

	public function test_mainwp_attention_summary_is_not_present_for_legacy_payloads() :void {
		$sync = ( new SyncVO() )->applyFromArray( [
			'integrity'   => [
				'status' => 'ok',
			],
			'scan_issues' => [
				'malware' => 2,
			],
		] );

		$this->assertFalse( $sync->hasMainwpAttentionSummary() );
	}
}
