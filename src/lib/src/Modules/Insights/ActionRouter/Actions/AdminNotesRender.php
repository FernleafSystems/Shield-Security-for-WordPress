<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\Build\AdminNotes;

class AdminNotesRender extends PluginBase {

	const SLUG = 'render_table_adminnotes';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$resp = $this->response();
		$resp->success = true;
		$resp->action_response_data = [
			'html'    => ( new AdminNotes() )
				->setMod( $mod )
				->setDbHandler( $mod->getDbHandler_Notes() )
				->render()
		];
	}
}