<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class PluginSetTracking extends PluginBase {

	const SLUG = 'set_plugin_tracking';

	protected function exec() {
		/** @var Options $opts */
		$opts = $this->primary_mod->getOptions();
		if ( !$opts->isTrackingPermissionSet() ) {
			$opts->setPluginTrackingPermission( (bool)Services::Request()->query( 'agree', false ) );
		}
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}