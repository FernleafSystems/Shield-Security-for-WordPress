<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class TranslationsForceDownload extends BaseAction {

	public const SLUG = 'translations_force_download';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		self::con()->comps->translation_downloads->processQueue( true );

		$this->response()->setPayload( [
			'page_reload' => true,
			'message'     => __( 'Translations have been downloaded.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( true );
	}
}
