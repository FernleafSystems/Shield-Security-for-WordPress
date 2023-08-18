<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForStats;

class InfoKeyStats extends BaseBuilder {

	public const SLUG = 'info_keystats';
	public const TEMPLATE = '/components/reports/components/info_keystats.twig';

	protected function getRenderData() :array {
		$report = $this->getReport();
		$counts = ( new BuildForStats( $report->interval_start_at, $report->interval_end_at ) )->build();

		$countsInRows = \array_chunk( $counts, 2 );

		return [
			'flags'   => [
				'render_required' => !empty( $counts ),
			],
			'strings' => [
				'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'counts' => $countsInRows
			],
		];
	}
}