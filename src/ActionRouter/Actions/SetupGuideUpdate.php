<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;

/**
 * Records a skipped setup step, or dismisses the setup guide for good, for the whole site. As it changes what
 * every admin sees, it needs Security Admin like any other change to the plugin.
 */
class SetupGuideUpdate extends BaseAction {

	public const SLUG = 'setup_guide_update';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$state = new SetupGuideState();
		switch ( sanitize_key( $this->fields()->string( 'op', '' ) ) ) {
			case 'skip':
				$saved = $state->markDone( $this->fields()->string( 'step_slug', '' ) );
				break;
			case 'dismiss':
				$state->dismiss();
				$saved = true;
				break;
			default:
				$saved = false;
				break;
		}
		$this->response()->setPayload( [
			'saved' => $saved,
		] )->setPayloadSuccess( $saved );
	}
}
