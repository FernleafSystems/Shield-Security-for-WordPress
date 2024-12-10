<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\HandleOptionsSaveRequest;

class ModuleOptionsSave extends BaseAction {

	public const SLUG = 'mod_options_save';

	protected function exec() {
		$con = self::con();

		$wasSecAdminEnabled = $con->comps->sec_admin->isEnabledSecAdmin();

		$success = ( new HandleOptionsSaveRequest() )->handleSave();

		$this->response()->action_response_data = [
			'success'     => $success,
			'html'        => '',
			'page_reload' => !$wasSecAdminEnabled && $con->comps->sec_admin->isEnabledSecAdmin(),
			// for Sec Admin activation
			'message'     => $success ?
				sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $con->labels->Name )
				: sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $con->labels->Name )
		];
	}
}