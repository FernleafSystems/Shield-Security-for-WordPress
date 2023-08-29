<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\HandleOptionsSaveRequest;

class ModuleOptionsSave extends BaseAction {

	public const SLUG = 'mod_options_save';

	protected function exec() {
		$con = self::con();
		$secAdminCon = $con->getModule_SecAdmin()->getSecurityAdminController();

		$wasSecAdminEnabled = $secAdminCon->isEnabledSecAdmin();

		$success = ( new HandleOptionsSaveRequest() )->handleSave();

		$this->response()->action_response_data = [
			'success'     => $success,
			'html'        => '',
			'page_reload' => !$wasSecAdminEnabled && $secAdminCon->isEnabledSecAdmin(), // for Sec Admin activation
			'message'     => $success ?
				sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $con->getHumanName() )
				: sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $con->getHumanName() )
		];
	}
}