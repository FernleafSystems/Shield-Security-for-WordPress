<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\CrowdSec;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;

class ForCrowdsecDecisions extends Base {

	protected function getOrderColumnSlug() :string {
		return 'date';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'ip',
			'date',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'ip'       => [
				'data'        => 'ip',
				'title'       => __( 'IP Address' ),
				'className'   => 'ip',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => true,
				],
			],
			'date'     => [
				'data'        => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'       => __( 'Date' ),
				'className'   => 'date',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
		];
	}
}