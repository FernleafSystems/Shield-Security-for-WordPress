<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NeedsAttentionQueueSummaryContractTest extends BaseUnitTest {

	public function test_extracts_summary_from_render_payload_contract() :void {
		$summary = NeedsAttentionQueue::summaryFromRenderPayload( [
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 4,
						'severity'    => 'critical',
						'icon_class'  => 'bi bi-exclamation-triangle-fill',
						'subtext'     => 'Last scan: 2 minutes ago',
					],
				],
			],
		] );

		$this->assertSame( true, $summary[ 'has_items' ] );
		$this->assertSame( 4, $summary[ 'total_items' ] );
		$this->assertSame( 'critical', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $summary[ 'icon_class' ] );
		$this->assertSame( 'Last scan: 2 minutes ago', $summary[ 'subtext' ] );
	}

	public function test_prefers_render_data_summary_contract_over_top_level_vars() :void {
		$summary = NeedsAttentionQueue::summaryFromRenderPayload( [
			'vars'        => [
				'summary' => [
					'has_items'   => false,
					'total_items' => 0,
					'severity'    => 'good',
					'icon_class'  => 'wrong-path',
					'subtext'     => 'wrong-path',
				],
			],
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 3,
						'severity'    => 'warning',
						'icon_class'  => 'from-render-data',
						'subtext'     => 'from-render-data',
					],
				],
			],
		] );

		$this->assertSame( true, $summary[ 'has_items' ] );
		$this->assertSame( 3, $summary[ 'total_items' ] );
		$this->assertSame( 'warning', $summary[ 'severity' ] );
		$this->assertSame( 'from-render-data', $summary[ 'icon_class' ] );
		$this->assertSame( 'from-render-data', $summary[ 'subtext' ] );
	}
}
