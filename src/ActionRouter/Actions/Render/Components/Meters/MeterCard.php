<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Handler
};

class MeterCard extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_progress_meter_card';
	public const TEMPLATE = '/wpadmin/components/progress_meter/meter.twig';

	protected function getRenderData() :array {
		$data = $this->action_data[ 'meter_data' ] ?? [];
		if ( empty( $data ) ) {
			$data = ( new Handler() )->getMeter( $this->action_data[ 'meter_slug' ] );
		}

		$percentage = (int)( $data[ 'totals' ][ 'percentage' ] ?? 0 );
		$traffic = BuildMeter::trafficFromPercentage( $percentage );
		$iconName = $traffic === 'good' ? 'shield-fill-check'
			: ( $traffic === 'critical' ? 'shield-fill-x' : 'exclamation-triangle' );

		return [
			'strings' => [
				'analysis'     => __( 'Analyse & Fix', 'wp-simple-firewall' ),
				'view_details' => __( 'View Details', 'wp-simple-firewall' ),
				'more'         => __( 'more...', 'wp-simple-firewall' ),
				'good'         => __( 'Good', 'wp-simple-firewall' ),
				'needs_work'   => __( 'Needs Work', 'wp-simple-firewall' ),
				'critical'     => __( 'Critical', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'svgs' => [
					'analysis'    => self::con()->svgs->raw( 'zoom-in' ),
					'status_icon' => self::con()->svgs->raw( $iconName ),
				],
			],
			'vars'    => [
				'meter_slug' => $this->action_data[ 'meter_slug' ],
				'meter'      => $data,
				'traffic'    => $traffic,
			],
		];
	}

	protected function getRenderTemplate() :string {
		if ( !empty( $this->action_data[ 'is_hero' ] ?? false ) ) {
			return '/wpadmin/components/progress_meter/meter_hero.twig';
		}
		return static::TEMPLATE;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'meter_slug',
		];
	}
}
