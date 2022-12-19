<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\HandleOptionsSaveRequest;

class ModuleOptionsSave extends BaseAction {

	public const SLUG = 'mod_options_save';

	protected function exec() {
		$con = $this->getCon();
		$saver = ( new HandleOptionsSaveRequest() )
			->setMod( $this->getMod() );
		$success = $saver->handleSave();

		$this->response()->action_response_data = [
			'success'     => $success,
			'redirect_to' => $con->plugin_urls->modOptionsCfg( $this->getMod() ),
			'html'        => '', //we reload the page
			'message'     => $success ?
				sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $con->getHumanName() )
				: sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $con->getHumanName() )
		];
	}
}