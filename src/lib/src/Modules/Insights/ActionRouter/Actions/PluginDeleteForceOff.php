<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class PluginDeleteForceOff extends PluginBase {

	public const SLUG = 'delete_forceoff';

	protected function exec() {
		$this->getCon()->deleteForceOffFile();
		$this->response()->action_response_data = [
			'success'     => true,
			'page_reload' => true,
			'message'     => __( 'Removed the forceoff file.', 'wp-simple-firewall' ),
		];
	}
}