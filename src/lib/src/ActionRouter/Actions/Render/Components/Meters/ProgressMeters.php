<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterOverallConfig,
	Meter\MeterSummary
};

class ProgressMeters extends BaseRender {

	public const SLUG = 'render_progress_meters';
	public const TEMPLATE = '/wpadmin_pages/insights/overview/progress_meter/progress_meters.twig';

	protected function getRenderData() :array {
		$componentBuilder = new Handler();

		$meters = [];
		$AR = self::con()->action_router;
		foreach ( $componentBuilder->getAllMeters() as $meterSlug => $meter ) {
			if ( !\in_array( $meterSlug, [ MeterSummary::SLUG, MeterOverallConfig::SLUG ] ) ) {
				$meters[ $meterSlug ] = $AR->render( MeterCard::SLUG, [
					'meter_slug' => $meterSlug,
					'meter_data' => $meter,
				] );
			}
		}

		return [
			'content' => [
				'primary_meter' => $AR->render( MeterCardPrimary::SLUG, [
					'meter_slug' => MeterSummary::SLUG,
					'meter_data' => $componentBuilder->getMeter( MeterSummary::class ),
				] ),
				'meters'        => $meters
			],
		];
	}
}