<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters;

/**
 * @deprecated 18.6
 */
class MeterCardPrimary extends MeterCard {

	public const SLUG = 'render_progress_meter_card_primary';

	protected function getRenderData() :array {
		$data = parent::getRenderData();
		$data[ 'vars' ][ 'display' ] = [
			'progress_chart_size'           => 180,
			'dimensions_column_chart'       => 'col-lg-5 col-xl-4',
			'dimensions_column_description' => 'col-lg-4 col-xl-5',
			'dimensions_column_analysis'    => 'col-lg-3 col-xl-3',
		];
		return $data;
	}
}