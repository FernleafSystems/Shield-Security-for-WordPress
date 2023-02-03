<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;

class SecurityAdminRemove extends SecurityAdminBase {

	public const SLUG = 'secadmin_remove_confirm';

	protected function exec() {
		( new RemoveSecAdmin() )
			->setMod( $this->primary_mod )
			->remove( (bool)$this->action_data[ 'quietly' ] ?? false );

		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => $this->getCon()->plugin_urls->adminHome(),
		];
	}
}