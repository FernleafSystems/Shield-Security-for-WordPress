<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\BaseRender,
	Actions\Traits\SecurityAdminRequired
};

class DbDescribeTable extends BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_utility_db_describe_table';
	public const TEMPLATE = '/utility/db_describe_table.twig';

	protected function getRenderData() :array {
		return [
			'vars' => [
				'show_table' => $this->action_data[ 'show_table' ],
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'show_table'
		];
	}
}