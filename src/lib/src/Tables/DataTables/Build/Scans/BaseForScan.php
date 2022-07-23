<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;

class BaseForScan extends Base {

	protected function getOrderColumnSlug() :string {
		return 'detected';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'file_as_href',
			'status',
			'file_type',
			'detected',
			'actions',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'           => [
				'data'       => 'rid',
				'title'      => 'ID',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => false,
			],
			'file'          => [
				'data'       => 'file',
				'title'      => __( 'File' ),
				'className'  => 'file',
				'orderable'  => true,
				'searchable' => true,
				'visible'    => true,
			],
			'file_as_href'  => [
				'data'       => [
					'_'    => 'file_as_href',
					'sort' => 'file',
				],
				'title'      => __( 'File' ),
				'className'  => 'file_as_href',
				'orderable'  => true,
				'searchable' => true,
				'visible'    => true,
			],
			'file_type'     => [
				'data'       => 'file_type',
				'title'      => __( 'Type' ),
				'className'  => 'file_type',
				'orderable'  => false,
				'searchable' => true,
				'visible'    => true,
			],
			'status'        => [
				'data'       => 'status',
				'title'      => __( 'Status' ),
				'className'  => 'status',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => true,
			],
			'detected'      => [
				'data'       => [
					'_'    => 'detected_since',
					'sort' => 'created_at',
				],
				'title'      => __( 'Detected' ),
				'className'  => 'detected',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => true,
			],
			'actions'       => [
				'data'       => 'actions',
				'title'      => __( 'Actions' ),
				'className'  => 'actions',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => true,
			],
			'mal_fp_confidence' => [
				'data'       => 'mal_fp_confidence',
				'title'      => __( 'False Positive Confidence' ),
				'className'  => 'mal_fp_confidence',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => true,
			],
			'line_numbers'  => [
				'data'       => 'line_numbers',
				'title'      => __( 'Line Numbers' ),
				'className'  => 'line_numbers',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => true,
			],
			'mal_sig'       => [
				'data'       => 'mal_sig',
				'title'      => __( 'Pattern Detected' ),
				'className'  => 'mal_sig',
				'orderable'  => false,
				'searchable' => true,
				'visible'    => true,
			],
		];
	}
}