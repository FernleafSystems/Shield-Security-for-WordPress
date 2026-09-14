<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;

/**
 * @phpstan-import-type ScanReportRow from BuildForScans
 */
trait BuildReportEmailFixture {

	protected function buildReportFixture( string $type = Constants::REPORT_TYPE_ALERT ) :ReportVO {
		$con = $this->requireController();

		$report = new ReportVO();
		$report->type = $type;
		$report->interval = 'daily';
		$report->start_at = 1710028800;
		$report->end_at = 1710115199;
		$report->previous_start_at = 1709942400;
		$report->previous_end_at = 1710028799;
		$report->title = $type === Constants::REPORT_TYPE_ALERT ? 'Alert :: Daily :: Auto-Generated' : 'Info :: Daily :: Auto-Generated';
		$report->areas = [
			Constants::REPORT_AREA_SCANS   => [ 'scan_results', 'scan_repairs' ],
			Constants::REPORT_AREA_STATS   => true,
			Constants::REPORT_AREA_CHANGES => true,
		];
		/** @var list<ScanReportRow> $scanResults */
		$scanResults = [
			[
				'slug'                    => 'afs_malware',
				'name'                    => 'Malware Scan',
				'count'                   => 4,
				'new_count'               => 2,
				'available'               => true,
				'colour'                  => 'warning',
				'has_count'               => true,
				'items_total'             => 4,
				'items'                   => [
					[ 'label' => '/wp-content/plugins/bad-plugin/malware.php', 'is_new' => true ],
					[ 'label' => '/wp-content/uploads/suspicious/payload.js', 'is_new' => true ],
				],
				'notification_target_ids' => [ 11, 12 ],
			],
			[
				'slug'                    => 'wpv',
				'name'                    => 'Vulnerability Scan',
				'count'                   => 1,
				'new_count'               => 1,
				'available'               => true,
				'colour'                  => 'warning',
				'has_count'               => true,
				'items_total'             => 1,
				'items'                   => [
					[ 'label' => 'Outdated dependency in Example Plugin', 'is_new' => true ],
				],
				'notification_target_ids' => [ 23 ],
			],
			[
				'slug'                    => 'apc',
				'name'                    => 'Abandoned Plugins',
				'count'                   => 0,
				'new_count'               => 0,
				'available'               => true,
				'colour'                  => 'success',
				'has_count'               => false,
				'items_total'             => 0,
				'items'                   => [],
				'notification_target_ids' => [],
			],
		];
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => $scanResults,
				'scan_repairs' => [
					'auto_repair' => [
						'name'    => 'Automatic Repairs',
						'count'   => 2,
						'repairs' => [
							'/wp-content/plugins/example-plugin/example.php',
							'/wp-content/themes/example-theme/functions.php',
						],
					],
				],
			],
			Constants::REPORT_AREA_STATS => [
				'security' => [
					'title'             => 'Security Stats',
					'has_non_zero_stat' => true,
					'stats'             => [
						'ip_offense' => [
							'name'                  => 'IP Offenses',
							'is_zero_stat'          => false,
							'count_current_period'  => 14,
							'count_previous_period' => 7,
							'count_diff_abs'        => 7,
							'diff_symbol_email'     => 'up',
							'diff_symbol_plus_minus'=> '+',
							'diff_percentage'       => 100,
							'diff_colour'           => 'warning',
						],
						'login_fail' => [
							'name'                  => 'Login Failures',
							'is_zero_stat'          => false,
							'count_current_period'  => 10,
							'count_previous_period' => 12,
							'count_diff_abs'        => 2,
							'diff_symbol_email'     => 'down',
							'diff_symbol_plus_minus'=> '-',
							'diff_percentage'       => -17,
							'diff_colour'           => 'success',
						],
					],
				],
			],
			Constants::REPORT_AREA_CHANGES => [
				'plugins' => [
					'title'    => 'Plugins',
					'total'    => 2,
					'detailed' => [
						[
							'name' => 'Example Plugin',
							'link' => [
								'href' => 'https://example.com/plugin/example-plugin',
								'text' => 'View',
							],
							'rows' => [
								[
									'lines' => [ 'Updated from 1.0.0 to 1.1.0' ],
									'count' => 1,
								],
								[
									'lines' => [ 'Automatic update completed' ],
									'count' => 1,
								],
							],
						],
						[
							'name' => 'Malware Cleanup Plugin',
							'link' => [],
							'rows' => [
								[
									'lines' => [ 'Activated' ],
									'count' => 1,
								],
							],
						],
					],
				],
				'users' => [
					'title'    => 'Users',
					'total'    => 1,
					'detailed' => [
						[
							'name' => 'New Administrator',
							'link' => [],
							'rows' => [
								[
									'lines' => [ 'Registered' ],
									'count' => 1,
								],
								[
									'lines' => [ 'Role: Administrator' ],
									'count' => 1,
								],
							],
						],
					],
				],
			],
		];

		$record = $con->db_con->reports->getRecord();
		$record->unique_id = \wp_generate_uuid4();
		$record->created_at = 1710201600;
		$report->record = $record;

		if ( $type === Constants::REPORT_TYPE_ALERT ) {
			$report->alert_digest = [
				'has_new_items'           => true,
				'notification_target_ids' => [ 11, 12, 23 ],
				'summary'                 => [
					'row_count'          => 2,
					'new_total'          => 3,
					'current_total'      => 5,
					'outstanding_total'  => 2,
					'actions_queue_href' => 'https://example.com/admin/scans',
				],
				'rows'          => [
					[
						'title'                    => 'Malware Scan',
						'count'                    => 4,
						'new_count'                => 2,
						'count_summary'            => '4 total, 2 new',
						'outstanding_count'        => 2,
						'has_new'                  => true,
						'new_items'                => [
							[ 'label' => '/wp-content/plugins/bad-plugin/malware.php' ],
							[ 'label' => '/wp-content/uploads/suspicious/payload.js' ],
						],
						'outstanding_items'        => [
							[ 'label' => '/wp-content/uploads/cache/legacy-shell.php' ],
						],
						'hidden_new_count'         => 0,
						'hidden_outstanding_count' => 1,
						'notification_target_ids'  => [ 11, 12 ],
						'review_href'              => 'https://example.com/admin/scans',
						'review_action'            => 'Review Scan Results',
					],
					[
						'title'                    => 'Vulnerability Scan',
						'count'                    => 1,
						'new_count'                => 1,
						'count_summary'            => '1 total, 1 new',
						'outstanding_count'        => 0,
						'has_new'                  => true,
						'new_items'                => [
							[ 'label' => 'Outdated dependency in Example Plugin' ],
						],
						'outstanding_items'        => [],
						'hidden_new_count'         => 0,
						'hidden_outstanding_count' => 0,
						'notification_target_ids'  => [ 23 ],
						'review_href'              => 'https://example.com/admin/scans',
						'review_action'            => 'Review Scan Results',
					],
				],
			];
		}
		else {
			$report->info_headline = [
				'summary' => [
					'title'    => '2 issues need attention',
					'subtitle' => 'Current alert status across your site.',
					'state'    => 'attention',
					'total_issues' => 2,
				],
				'cards'   => [
					[
						'key'    => 'attention',
						'label'  => 'Alert Status',
						'value'  => '2 issues need attention',
						'meta'   => 'Current issues requiring attention.',
						'state'  => 'attention',
						'severity' => 'critical',
						'total_issues' => 2,
					],
					[
						'key'    => 'coverage',
						'label'  => 'Configuration Coverage',
						'value'  => '82% configured',
						'meta'   => '1 critical zone, 2 zones need review, 3 zones ready',
						'severity' => 'warning',
						'percentage' => 82,
						'zones'  => [
							'total'    => 6,
							'good'     => 3,
							'warning'  => 2,
							'critical' => 1,
						],
					],
					[
						'key'    => 'scans',
						'label'  => 'Scan Status',
						'value'  => 'Last scan: 3 hours ago',
						'meta'   => 'No scans are currently running.',
						'state'  => 'completed',
						'enqueued_count' => 0,
						'latest_completed_at' => 1710104400,
					],
				],
			];
		}

		return $report;
	}
}
