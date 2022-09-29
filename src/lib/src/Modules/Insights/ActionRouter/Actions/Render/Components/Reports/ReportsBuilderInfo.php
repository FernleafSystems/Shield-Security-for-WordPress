<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render;

class ReportsBuilderInfo extends Render\BaseRender {

	const SLUG = 'reports_builder_info';
	const TEMPLATE = '/components/reports/info_body.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'title'            => __( 'Site Information Report', 'wp-simple-firewall' ),
				'subtitle'         => __( 'The following is a collection of the latest information based on your reporting settings.', 'wp-simple-firewall' ),
				'dates_below'      => __( 'Information is for the following time period.', 'wp-simple-firewall' ),
				'reporting_period' => __( 'Reporting Period', 'wp-simple-firewall' ),
			],
		];
	}
}