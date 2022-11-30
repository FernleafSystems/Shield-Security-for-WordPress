<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;

class SecurityAdminRemoveByEmail extends SecurityAdminBase {

	public const SLUG = 'secadmin_remove_confirm';

	protected function exec() {
		( new RemoveSecAdmin() )
			->setMod( $this->primary_mod )
			->remove();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'Security Admin restriction removed.' ),
		];
	}
}