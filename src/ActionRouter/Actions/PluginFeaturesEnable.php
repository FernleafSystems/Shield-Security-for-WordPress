<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

/**
 * Turns all Shield protection back on after it was switched off with the global on/off setting.
 */
class PluginFeaturesEnable extends BaseAction {

	public const SLUG = 'plugin_features_enable';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		self::con()->opts->optSet( 'global_enable_plugin_features', 'Y' )->store();
		$this->response()->setPayload( [
			'message'     => __( 'Shield protection is turned back on.', 'wp-simple-firewall' ),
			'page_reload' => true,
		] )->setPayloadSuccess( true );
	}
}
