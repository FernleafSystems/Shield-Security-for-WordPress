<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

class FormScanResultsDisplayOptions extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_form_scan_results_display_options';
	public const TEMPLATE = '/components/forms/scan_results_display_options.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'include_ignored'  => __( 'Include Ignored Results', 'wp-simple-firewall' ),
				'include_repaired' => __( 'Include Repaired Results.', 'wp-simple-firewall' ),
				'include_deleted'  => __( 'Include Deleted Results', 'wp-simple-firewall' ),
				'submit'           => __( 'Update Display Options', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'current' => self::con()->opts->optGet( 'scan_results_table_display' ),
			],
		];
	}
}