<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class PluginSetTracking extends BaseAction {

	public const SLUG = 'set_plugin_tracking';

	protected function exec() {
		/** @var Options $opts */
		$opts = $this->con()->getModule_Plugin()->getOptions();
		if ( !$opts->isTrackingPermissionSet() ) {
			$opts->setPluginTrackingPermission( (bool)Services::Request()->query( 'agree', false ) );
		}
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}