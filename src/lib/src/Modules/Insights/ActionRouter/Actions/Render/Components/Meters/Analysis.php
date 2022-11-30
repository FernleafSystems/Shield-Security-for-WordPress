<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Meters;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;

class Analysis extends BaseRender {

	public const SLUG = 'render_progress_meter_analysis';
	public const TEMPLATE = '/wpadmin_pages/insights/overview/progress_meter/analysis/standard.twig';

	protected function getRenderData() :array {
		$components = $this->action_data[ 'meter_components' ];
		return [
			'strings' => [
				'title'            => sprintf( '%s: %s', __( 'Analysis', 'wp-simple-firewall' ), $components[ 'meter_title' ] ),
				'total_score'      => __( 'Total Score', 'wp-simple-firewall' ),
				'scores_footnote1' => __( 'Scores are an approximate weighting for each component.', 'wp-simple-firewall' ),
				'scores_footnote2' => __( 'As each issue is resolved the overall score will improve, up to 100%.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'total_percentage_score' => $components[ 'totals' ][ 'percentage' ],
				'components'             => $components[ 'components' ],
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'meter_components',
		];
	}
}