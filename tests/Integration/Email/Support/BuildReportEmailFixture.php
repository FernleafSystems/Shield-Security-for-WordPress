<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;

trait BuildReportEmailFixture {

	protected function buildReportFixture( string $type = Constants::REPORT_TYPE_ALERT ) :ReportVO {
		$con = $this->requireController();

		$report = new ReportVO();
		$report->type = $type;
		$report->interval = 'daily';
		$report->start_at = 1710028800;
		$report->end_at = 1710115199;
		$report->title = $type === Constants::REPORT_TYPE_ALERT ? 'Alert :: Daily :: Auto-Generated' : 'Info :: Daily :: Auto-Generated';
		$report->areas = [
			Constants::REPORT_AREA_SCANS   => [ 'scan_results', 'scan_repairs' ],
			Constants::REPORT_AREA_STATS   => true,
			Constants::REPORT_AREA_CHANGES => true,
		];
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => [
					[
						'slug'       => 'afs_malware',
						'name'       => 'Malware Scan',
						'count'      => 4,
						'new_count'  => 2,
						'colour'     => 'warning',
						'items_total'=> 4,
						'items'      => [
							[ 'label' => '/wp-content/plugins/bad-plugin/malware.php', 'is_new' => true ],
							[ 'label' => '/wp-content/uploads/suspicious/payload.js', 'is_new' => true ],
						],
					],
					[
						'slug'       => 'wpv',
						'name'       => 'Vulnerability Scan',
						'count'      => 1,
						'new_count'  => 1,
						'colour'     => 'warning',
						'items_total'=> 1,
						'items'      => [
							[ 'label' => 'Outdated dependency in Example Plugin', 'is_new' => true ],
						],
					],
					[
						'slug'      => 'apc',
						'name'      => 'Abandoned Plugins',
						'count'     => 0,
						'new_count' => 0,
						'colour'    => 'success',
						'items'     => [],
					],
				],
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
								'Updated from 1.0.0 to 1.1.0',
								'Automatic update completed',
							],
						],
						[
							'name' => 'Malware Cleanup Plugin',
							'rows' => [
								'Activated',
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
							'rows' => [
								'Registered',
								'Role: Administrator',
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
				'has_new_items' => true,
				'summary'       => [
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
				],
				'cards'   => [
					[
						'label'  => 'Alert Status',
						'value'  => '2 issues need attention',
						'meta'   => 'Current critical issues requiring attention.',
					],
					[
						'label'  => 'Security Posture',
						'value'  => '82% configured',
						'meta'   => 'Current posture grade: B',
					],
					[
						'label'  => 'Scan Status',
						'value'  => 'Last scan: 3 hours ago',
						'meta'   => 'No scans are currently running.',
					],
				],
			];
		}

		return $report;
	}
}
