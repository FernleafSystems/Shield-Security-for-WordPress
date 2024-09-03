<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginSetOpt extends BaseAction {

	public const SLUG = 'plugin_set_opt';

	protected function exec() {
		self::con()->opts->optSet( $this->action_data[ 'opt_key' ], $this->action_data[ 'opt_value' ] );
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}