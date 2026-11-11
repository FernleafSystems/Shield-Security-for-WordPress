<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class PluginSetTracking extends BaseAction {

	public const SLUG = 'set_plugin_tracking';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		self::con()
			->opts
			->optSet( 'enable_tracking', ( $this->action_data[ 'agree' ] ?? false ) ? 'Y' : 'N' );
		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}
