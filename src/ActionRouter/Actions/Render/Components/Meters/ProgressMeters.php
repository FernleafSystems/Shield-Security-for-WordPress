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
	public const TEMPLATE = '/wpadmin/components/progress_meter/progress_meters.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'strings' => [
				'good'       => __( 'Good', 'wp-simple-firewall' ),
				'needs_work' => __( 'Needs Work', 'wp-simple-firewall' ),
				'critical'   => __( 'Critical', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'svgs' => [
					'icon_good'     => $con->svgs->raw( 'shield-fill-check' ),
					'icon_warning'  => $con->svgs->raw( 'exclamation-triangle' ),
					'icon_critical' => $con->svgs->raw( 'shield-fill-x' ),
				],
			],
			'vars'    => [
				'meter_slugs'        => \array_diff( \array_keys( Handler::METERS ), [
					MeterSummary::SLUG,
					MeterOverallConfig::SLUG
				] ),
				'primary_meter_slug' => MeterSummary::SLUG,
			],
		];
	}
}