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
		// The notice sends true or false, which arrive as the text "true" or "false". Reading that text as a
		// boolean once made "false" switch tracking on.
		self::con()
			->opts
			->optSet( 'enable_tracking', $this->fields()->flag( 'agree', 'true', 'false' ) ? 'Y' : 'N' );
		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}
