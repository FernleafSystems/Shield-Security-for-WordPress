<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class PluginSetTracking extends BaseAction {

	public const SLUG = 'set_plugin_tracking';

	protected function exec() {
		/** @var Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		if ( !$opts->isTrackingPermissionSet() ) {
			$opts->setPluginTrackingPermission( (bool)$this->action_data[ 'agree' ] ?? false );
		}
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}