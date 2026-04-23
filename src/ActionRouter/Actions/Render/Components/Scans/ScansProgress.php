<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

class ScansProgress extends BaseScans {

	public const SLUG = 'render_scans_progress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/progress_snippet.twig';

	protected function getRenderData() :array {
		$isFailed = !empty( $this->action_data[ 'is_failed' ] );

		return [
			'strings' => [
				'current_scan' => __( 'Current Scan', 'wp-simple-firewall' ),
				'patience_1'   => __( 'File scanning is an intensive operation and takes time.', 'wp-simple-firewall' ),
				'patience_2'   => __( 'We appreciate your patience.', 'wp-simple-firewall' ),
				'completed'    => __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...',
				'failed'       => __( 'Scan failed.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'current_scan'    => $this->action_data[ 'current_scan' ],
				'remaining_scans' => $this->action_data[ 'remaining_scans' ],
				'progress'        => $this->action_data[ 'progress' ],
				'is_failed'       => $isFailed,
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
