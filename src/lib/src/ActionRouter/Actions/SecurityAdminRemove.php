<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;

class SecurityAdminRemove extends SecurityAdminBase {

	public const SLUG = 'secadmin_remove_confirm';

	protected function exec() {
		$con = $this->getCon();
		( new RemoveSecAdmin() )
			->setMod( $con->getModule_SecAdmin() )
			->remove( (bool)$this->action_data[ 'quietly' ] ?? false );

		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => $con->plugin_urls->modCfg( $con->getModule_SecAdmin() ),
		];
	}
}