<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ResolveReportViewContracts;
class EmailReportAlert extends EmailReportBase {

	public const SLUG = 'email_report_alert';
	public const TEMPLATE = '/email/reports/alert_report.twig';

	protected function getBodyData() :array {
		$report = $this->report();
		$digest = ( new ResolveReportViewContracts() )->alertDigest( $report );

		return [
			'vars'    => [
				'site_url'     => $this->action_data[ 'home_url' ],
				'report'       => \array_merge(
					$this->buildReportMeta( $report ),
					[ 'digest' => $digest ]
				),
			],
			'strings' => \array_merge(
				$this->commonStrings(),
				[
					'intro_text'         => __( 'These are the new critical issues on your site, followed by previously reported critical issues that are still unresolved.', 'wp-simple-firewall' ),
					'new_issues'         => __( 'New Issues', 'wp-simple-firewall' ),
					'outstanding_issues' => __( 'Still Outstanding', 'wp-simple-firewall' ),
					'current_issues'     => __( 'Current Critical Issues', 'wp-simple-firewall' ),
				]
			),
		];
	}
}
