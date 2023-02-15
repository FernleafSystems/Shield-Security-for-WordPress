<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\Build\Sessions;

class UserSessionsTableRender extends SecurityAdminBase {

	public const SLUG = 'render_table_sessions';

	protected function exec() {
		$this->response()->action_response_data = [
			'success' => true,
			'html'    => ( new Sessions() )
				->setMod( $this->getMod() )
				->render()
		];
	}
}