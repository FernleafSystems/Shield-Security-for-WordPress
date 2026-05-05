<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForImportExportSites extends Base {

	protected function getOrderColumnSlug() :string {
		return 'updated_at';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'url',
			'status',
			'queue_status',
			'last_ping_attempt',
			'last_ping_success',
			'last_ping_failure',
			'last_export_request',
			'last_export_success',
			'last_export_failure',
			'last_ping_http_code',
			'last_export_result_code',
			'consecutive_failures',
			'details',
			'updated_at',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'url'                     => [
				'data'        => 'url',
				'title'       => __( 'URL', 'wp-simple-firewall' ),
				'className'   => 'url',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'status'                  => [
				'data'        => 'status',
				'title'       => __( 'Registration', 'wp-simple-firewall' ),
				'className'   => 'status',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'queue_status'            => [
				'data'        => 'queue_status',
				'title'       => __( 'Queue', 'wp-simple-firewall' ),
				'className'   => 'queue_status',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'last_ping_attempt'       => $this->timestampColumn( 'last_ping_attempt', 'last_ping_attempt_at', __( 'Last Ping Attempt', 'wp-simple-firewall' ) ),
			'last_ping_success'       => $this->timestampColumn( 'last_ping_success', 'last_ping_success_at', __( 'Last Ping Success', 'wp-simple-firewall' ) ),
			'last_ping_failure'       => $this->timestampColumn( 'last_ping_failure', 'last_ping_failure_at', __( 'Last Ping Failure', 'wp-simple-firewall' ) ),
			'last_export_request'     => $this->timestampColumn( 'last_export_request', 'last_export_request_at', __( 'Last Export Request', 'wp-simple-firewall' ) ),
			'last_export_success'     => $this->timestampColumn( 'last_export_success', 'last_export_success_at', __( 'Last Export Success', 'wp-simple-firewall' ) ),
			'last_export_failure'     => $this->timestampColumn( 'last_export_failure', 'last_export_failure_at', __( 'Last Export Failure', 'wp-simple-firewall' ) ),
			'last_ping_http_code'     => [
				'data'        => 'last_ping_http_code',
				'title'       => __( 'Ping HTTP', 'wp-simple-firewall' ),
				'className'   => 'last_ping_http_code',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'last_export_result_code' => [
				'data'        => 'last_export_result_code',
				'title'       => __( 'Export Result', 'wp-simple-firewall' ),
				'className'   => 'last_export_result_code',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'consecutive_failures'    => [
				'data'        => 'consecutive_failures',
				'title'       => __( 'Failures', 'wp-simple-firewall' ),
				'className'   => 'consecutive_failures',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'details'                 => [
				'data'        => 'details',
				'title'       => __( 'Details', 'wp-simple-firewall' ),
				'className'   => 'details',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'updated_at'              => [
				'data'        => 'updated_at',
				'title'       => __( 'Updated At', 'wp-simple-firewall' ),
				'className'   => 'updated_at',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [ 'show' => false ],
			],
		];
	}

	private function timestampColumn( string $dataKey, string $sortKey, string $title ) :array {
		return [
			'data'          => [
				'_'    => $dataKey,
				'sort' => $sortKey,
			],
			'title'         => $title,
			'className'     => $dataKey,
			'orderable'     => true,
			'orderSequence' => [ 'desc', 'asc' ],
			'searchable'    => false,
			'visible'       => true,
			'searchPanes'   => [ 'show' => false ],
		];
	}
}
