<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

class ReportAreaStats extends ReportAreaBase {

	public const SLUG = 'report_area_stats';
	public const TEMPLATE = '/reports/areas/stats.twig';

	protected function getRenderData() :array {
		$stats = $this->report()->areas_data[ Constants::REPORT_AREA_STATS ];
		$hasAnyStats = false;
		foreach ( $stats as $stat ) {
			$hasAnyStats = $hasAnyStats || $stat[ 'has_non_zero_stat' ];
		}

		return [
			'flags' => [
				'has_stats' => $hasAnyStats,
			],
			'vars'  => [
				'stats' => $stats,
			],
		];
	}
}