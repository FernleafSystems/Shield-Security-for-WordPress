<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;

class MerlinAction extends BaseAction {

	public const SLUG = 'merlin_action';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$form = $this->formParams();
		try {
			$response = self::con()->comps->wizards->processFormSubmit( $form );
			$success = $response->success;
			$msg = $response->getRelevantMsg();
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		// The setup guide and the wizard share these steps, so a completed step counts in both.
		if ( $success ) {
			$stepSlug = $form[ 'step_slug' ] ?? '';
			( new SetupGuideState() )->markDone( \is_string( $stepSlug ) ? $stepSlug : '' );
		}

		$this->response()->setPayload( [
			'message'     => $msg,
			'page_reload' => $response->data[ 'page_reload' ] ?? false,
			'show_toast'  => true,
		] )->setPayloadSuccess( $success );
	}
}
