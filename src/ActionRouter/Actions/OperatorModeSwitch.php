<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\OperatorModePreference;

class OperatorModeSwitch extends SecurityAdminBase {

	public const SLUG = 'operator_mode_switch';

	protected function exec() {
		$pref = new OperatorModePreference();
		$pref->setCurrent( (string)( $this->action_data[ 'mode' ] ?? '' ) );

		if ( self::con()->this_req->wp_is_ajax ) {
			$this->response()->action_response_data = [
				'success'     => true,
				'page_reload' => false,
				'mode'        => $pref->getCurrent(),
			];
			return;
		}

		$this->response()->action_response_data = [
			'success' => true,
		];
		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => self::con()->plugin_urls->adminRefererOrHome(),
		];
	}
}
