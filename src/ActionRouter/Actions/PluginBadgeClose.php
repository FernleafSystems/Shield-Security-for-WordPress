<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class PluginBadgeClose extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'plugin_badge_close';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$success = self::con()->comps->badge->setBadgeStateClosed();
		$this->response()->setPayload( [
			'message' => $success
				? __( 'Badge Closed', 'wp-simple-firewall' )
				: __( 'Badge Not Closed', 'wp-simple-firewall' )
		] )->setPayloadSuccess( $success );
	}
}
