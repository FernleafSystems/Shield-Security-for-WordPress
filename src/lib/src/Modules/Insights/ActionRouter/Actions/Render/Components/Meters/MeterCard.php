<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Meters;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;

class MeterCard extends BaseRender {

	const SLUG = 'render_progress_meter_card';
	const TEMPLATE = '/wpadmin_pages/insights/overview/progress_meter/meter_card.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'strings' => [
				'analysis' => __( 'Analysis', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'svgs' => [
					'analysis' => $con->svgs->raw( 'bootstrap/clipboard2-data-fill.svg' ),
				],
			],
			'vars'    => [
				'meter_slug' => $this->action_data[ 'meter_slug' ],
				'meter'      => $this->action_data[ 'meter_data' ],
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'meter_slug',
			'meter_data',
		];
	}
}