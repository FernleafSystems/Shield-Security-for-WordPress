<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\HandleOptionsSaveRequest;

class ModuleOptionsSave extends BaseAction {

	const SLUG = 'mod_options_save';

	protected function exec() {
		$name = $this->getCon()->getHumanName();
		$saver = ( new HandleOptionsSaveRequest() )
			->setMod( $this->getMod() );
		$success = $saver->handleSave();

		$this->response()->action_response_data = [
			'success'     => $success,
			'redirect_to' => $saver->getMod()->getUrl_OptionsConfigPage(),
			'html'        => '', //we reload the page
			'message'     => $success ?
				sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $name )
				: sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $name )
		];
	}
}