<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginDeleteForceOff extends BaseAction {

	public const SLUG = 'delete_forceoff';

	protected function exec() {
		self::con()->deleteForceOffFile();
		$this->response()->action_response_data = [
			'success'     => true,
			'page_reload' => true,
			'message'     => __( 'Removed the forceoff file.', 'wp-simple-firewall' ),
		];
	}
}