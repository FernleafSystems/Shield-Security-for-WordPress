<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class PluginSetTracking extends BaseAction {

	public const SLUG = 'set_plugin_tracking';

	protected function exec() {
		/** @var Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		if ( !$opts->isTrackingPermissionSet() ) {
			self::con()
				->opts
				->optSet( 'enable_tracking', ($this->action_data[ 'agree' ] ?? false) ? 'Y' : 'N' )
				->optSet( 'tracking_permission_set_at', Services::Request()->ts() );
		}
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}