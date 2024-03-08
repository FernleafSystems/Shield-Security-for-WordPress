<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForSecurityRules extends Base {

	protected function getOrderColumnSlug() :string {
		return 'exec_order';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'active',
			'rid',
			'uuid',
			'exec_order',
			'details',
			'name',
			'description',
			'version',
			'is_viable',
			'actions',
			'date',
			'drag',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'active'      => [
				'data'           => 'active',
				'title'          => __( 'Active' ),
				'className'      => 'active',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'rid'         => [
				'data'           => 'rid',
				'title'          => __( 'ID' ),
				'className'      => 'rid',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'uuid'        => [
				'data'           => 'uuid',
				'title'          => __( 'Unique ID' ),
				'className'      => 'uuid',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'exec_order'  => [
				'data'           => 'exec_order',
				'title'          => __( 'Exec Order' ),
				'className'      => 'exec_order',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'details'     => [
				'data'           => 'details',
				'title'          => __( 'Details' ),
				'className'      => 'details',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'name'        => [
				'data'           => 'name',
				'title'          => __( 'Name' ),
				'className'      => 'name',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'description' => [
				'data'           => 'description',
				'title'          => __( 'Description' ),
				'className'      => 'description',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'version'     => [
				'data'           => 'version',
				'title'          => __( 'Version' ),
				'className'      => 'version',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true
				],
			],
			'is_viable'   => [
				'data'           => 'is_viable',
				'title'          => __( 'Is Viable' ),
				'className'      => 'is_viable',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true
				],
			],
			'date'        => [
				'data'          => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'         => __( 'Modified' ),
				'className'     => 'date',
				'orderable'     => false,
				'orderSequence' => [ 'desc', 'asc' ],
				'searchable'    => false,
				'visible'       => true,
				'searchPanes'   => [
					'show' => false
				],
			],
			'actions'     => [
				'data'        => 'actions',
				'title'       => __( 'Actions' ),
				'className'   => 'actions',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'drag'        => [
				'data'           => 'drag',
				'title'          => '',
				'className'      => 'drag',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
		];
	}
}