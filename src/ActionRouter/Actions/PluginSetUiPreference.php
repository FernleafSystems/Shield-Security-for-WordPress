<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\UiPreferences;

class PluginSetUiPreference extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'set_ui_preference';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$saved = ( new UiPreferences() )->set(
			sanitize_key( (string)( $this->action_data[ 'pref_key' ] ?? '' ) ),
			sanitize_key( (string)( $this->action_data[ 'pref_value' ] ?? '' ) )
		);
		$this->response()->setPayload( [
			'saved' => $saved,
		] )->setPayloadSuccess( $saved );
	}
}
