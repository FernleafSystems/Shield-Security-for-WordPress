<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\OperatorModePreference;

class OperatorModeSwitch extends SecurityAdminBase {

	public const SLUG = 'operator_mode_switch';

	protected function exec() {
		$pref = new OperatorModePreference();
		$pref->setCurrent( (string)( $this->action_data[ 'mode' ] ?? '' ) );

		if ( self::con()->this_req->wp_is_ajax ) {
			$this->response()
				->setPayload( [
					'page_reload' => false,
					'mode'        => $pref->getCurrent(),
				] )
				->setPayloadSuccess( true );
			return;
		}

		$this->response()
			->setPayloadSuccess( true )
			->setPayloadRedirectNextStep( self::con()->plugin_urls->adminRefererOrHome() );
	}
}
