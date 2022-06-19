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
			'ip_linked',
			'last_seen',
			'auto_unblock_at',
			'last_access_at',
			'date',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'ip'        => [
				'data'        => 'ip',
				'title'       => __( 'IP Address' ),
				'className'   => 'ip',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => true,
				],
			],
			'ip_linked'  => [
				'data'        => 'ip_linked',
				'title'       => __( 'IP' ),
				'className'   => 'ip_linked',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
			'last_seen' => [
				'data'        => [
					'_'    => 'last_seen',
					'sort' => 'last_access_at',
				],
				'title'       => __( 'Last Seen' ),
				'className'   => 'date',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'last_access_at' => [
				'data'        => 'last_access_at',
				'title'       => __( 'Last Access At' ),
				'className'   => 'date',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => false
				],
			],
			'auto_unblock_at' => [
				'data'        => 'auto_unblock_at',
				'title'       => __( 'Unblocked At' ),
				'className'   => 'date',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'date'      => [
				'data'        => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'       => __( 'Date Added' ),
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