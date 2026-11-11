<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;

/**
 * Records a skipped setup step, or dismisses the setup guide for good, for the current user.
 */
class SetupGuideUpdate extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'setup_guide_update';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$state = new SetupGuideState();
		switch ( sanitize_key( (string)( $this->action_data[ 'op' ] ?? '' ) ) ) {
			case 'skip':
				$saved = $state->markDone( (string)( $this->action_data[ 'step_slug' ] ?? '' ) );
				break;
			case 'dismiss':
				$saved = $state->dismiss();
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
