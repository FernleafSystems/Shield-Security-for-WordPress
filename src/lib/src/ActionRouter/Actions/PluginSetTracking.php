<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginSetTracking extends BaseAction {

	public const SLUG = 'set_plugin_tracking';

	protected function exec() {
		self::con()
			->opts
			->optSet( 'enable_tracking', ( $this->action_data[ 'agree' ] ?? false ) ? 'Y' : 'N' );
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}