<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\Build\Sessions;

class UserSessionsTableRender extends SecurityAdminBase {

	const SLUG = 'render_table_sessions';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		/** @var Options $optsSecAdmin */
		$optsSecAdmin = $this->getCon()->getModule_SecAdmin()->getOptions();
		$this->response()->action_response_data = [
			'success' => true,
			'html'    => ( new Sessions() )
				->setMod( $this->primary_mod )
				->setSecAdminUsers( $optsSecAdmin->getSecurityAdminUsers() )
				->render()
		];
	}
}