<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;

class MeterCard extends BaseRender {

	public const SLUG = 'render_progress_meter_card';
	public const TEMPLATE = '/wpadmin/components/progress_meter/meter.twig';

	protected function getRenderData() :array {
		$data = $this->action_data[ 'meter_data' ] ?? [];
		if ( empty( $data ) ) {
			$data = ( new Handler() )->getMeter( $this->action_data[ 'meter_slug' ] );
		}

		return [
			'strings' => [
				'analysis' => __( 'Analysis', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'svgs' => [
					'analysis' => self::con()->svgs->raw( 'clipboard2-data-fill.svg' ),
				],
			],
			'vars'    => [
				'meter_slug' => $this->action_data[ 'meter_slug' ],
				'meter'      => $data,
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'meter_slug',
		];
	}
}