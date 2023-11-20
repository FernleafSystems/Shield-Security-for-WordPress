<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

/**
 * @deprecated 18.5
 */
class UserSessionsTableRender extends SecurityAdminBase {

	public const SLUG = 'render_table_sessions';

	protected function exec() {
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}