<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans;

class ScansProgress extends BaseScans {

	public const SLUG = 'render_scans_progress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/progress_snippet.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'current_scan' => __( 'Current Scan', 'wp-simple-firewall' ),
				'patience_1'   => __( 'Please be patient.', 'wp-simple-firewall' ),
				'patience_2'   => __( 'Some scans can take quite a while to complete.', 'wp-simple-firewall' ),
				'completed'    => __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...'
			],
			'vars'    => [
				'current_scan'    => $this->action_data[ 'current_scan' ],
				'remaining_scans' => $this->action_data[ 'remaining_scans' ],
				'progress'        => $this->action_data[ 'progress' ],
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'current_scan',
			'remaining_scans',
			'progress',
		];
	}
}