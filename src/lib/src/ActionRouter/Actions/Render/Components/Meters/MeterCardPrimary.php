<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters;

class MeterCardPrimary extends MeterCard {

	public const SLUG = 'render_progress_meter_card_primary';

	protected function getRenderData() :array {
		$con = self::con();
		$data = parent::getRenderData();
		$data[ 'vars' ][ 'display' ] = [
			'progress_chart_size'           => 180,
			'dimensions_column_chart'       => 'col-lg-4 col-xl-3',
			'dimensions_column_description' => 'col-lg-5 col-xl-6',
			'dimensions_column_analysis'    => 'col-lg-3 col-xl-3',
		];
		return $data;
	}
}