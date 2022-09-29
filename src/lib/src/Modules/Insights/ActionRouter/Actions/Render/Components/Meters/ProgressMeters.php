<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Meters;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\MeterIntegrity;

class ProgressMeters extends BaseRender {

	const SLUG = 'render_progress_meters';
	const TEMPLATE = '/wpadmin_pages/insights/overview/progress_meter/progress_meters.twig';

	protected function getRenderData() :array {
		$componentBuilder = ( new Handler() )->setMod( $this->getMod() );

		$meters = [];
		$AR = $this->getCon()
				   ->getModule_Insights()
				   ->getActionRouter();
		foreach ( $componentBuilder->buildAllMeterComponents() as $meterSlug => $meter ) {
			$meters[ $meterSlug ] = $AR->render( MeterCard::SLUG, [
				'meter_slug' => $meterSlug,
				'meter_data' => $meter,
			] );
		}

		$primaryMeter = $meters[ MeterIntegrity::SLUG ];
		unset( $meters[ MeterIntegrity::SLUG ] );

		return [
			'content' => [
				'primary_meter' => $primaryMeter,
				'meters'        => $meters
			],
		];
	}
}