<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

class CustomChartData extends BaseBuildChartData {

	/**
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function build() :array {
		$this->preProcessRequest();

		$allSeries = [];
		foreach ( $this->getChartRequest()->events as $event ) {
			$allSeries[] = $this->buildDataForEvents( [ $event ] );
		}
		error_log( var_export( $allSeries, true ) );
		return [
			'data'         => [
				'labels' => [],
				'series' => [
					array_map( 'array_reverse', $allSeries ),
				]
			],
			'legend_names' => [],
		];
	}
}