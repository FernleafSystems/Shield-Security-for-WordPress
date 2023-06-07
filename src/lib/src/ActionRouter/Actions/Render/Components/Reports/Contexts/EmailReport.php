<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\EmailBase;
use FernleafSystems\Wordpress\Services\Services;

class EmailReport extends EmailBase {

	public const SLUG = 'email_report';
	public const TEMPLATE = '/email/reports/cron_alert_info_report.twig';

	protected function getBodyData() :array {
		$con = $this->con();
		return [
			'content' => [
				'reports' => $this->action_data[ 'reports' ]
			],
			'vars'    => [
				'site_url'    => $this->action_data[ 'home_url' ],
				'report_date' => Services::WpGeneral()->getTimeStampForDisplay(),
			],
			'hrefs'   => [
				'click_adjust' => $con->plugin_urls->modCfgSection( $con->getModule_Plugin(), 'section_reporting' )
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