<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SyncVOTest extends BaseUnitTest {

	public function test_mainwp_issues_summary_normalizes_attention_contract() :void {
		$summary = ( new SyncVO() )->applyFromArray( [
			'overview' => [
				'attention_summary' => [
					'total'    => 4,
					'severity' => 'warning',
				],
			],
		] )
								   ->mainwpIssuesSummary();

		$this->assertSame( [
			'count'        => 4,
			'severity'     => 'warning',
			'has_issues'   => true,
			'button_class' => 'orange',
		], $summary );
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
}
