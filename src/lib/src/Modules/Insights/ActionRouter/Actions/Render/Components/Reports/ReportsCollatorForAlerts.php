<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports;

class ReportsCollatorForAlerts extends ReportsCollatorBase {

	public const SLUG = 'reports_builder_alerts';
	public const TEMPLATE = '/components/reports/alert_body.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'title'    => __( 'Important Alerts', 'wp-simple-firewall' ),
				'subtitle' => __( 'The following is a collection of the latest alerts since your previous report.', 'wp-simple-firewall' ),
			],
		];
	}
}