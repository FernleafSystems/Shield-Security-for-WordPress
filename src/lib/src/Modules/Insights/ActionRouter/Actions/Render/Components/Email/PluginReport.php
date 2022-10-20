<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Services\Services;

class PluginReport extends Base {

	const SLUG = 'email_report';
	const TEMPLATE = '/email/reports/cron_alert_info_report.twig';

	protected function getBodyData() :array {
		return [
			'content' => [
				'reports' => $this->action_data[ 'reports' ]
			],
			'vars'    => [
				'site_url'    => $this->action_data[ 'home_url' ],
				'report_date' => Services::WpGeneral()->getTimeStampForDisplay(),
			],
			'hrefs'   => [
				'click_adjust' => $this->getCon()
									   ->getModule_Insights()
									   ->getUrl_SubInsightsPage( 'reports' )
			],
			'strings' => [
				'please_find'  => __( 'Please find your site report below.', 'wp-simple-firewall' ),
				'depending'    => __( 'Depending on your settings and cron timings, this report may contain a combination of alerts, statistics and other information.', 'wp-simple-firewall' ),
				'site_url'     => __( 'Site URL', 'wp-simple-firewall' ),
				'report_date'  => __( 'Report Generation Date', 'wp-simple-firewall' ),
				'use_links'    => __( 'Please use links provided in each section to review the report details.', 'wp-simple-firewall' ),
				'click_adjust' => __( 'Click here to adjust your reporting settings', 'wp-simple-firewall' ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'home_url',
			'reports',
		];
	}
}