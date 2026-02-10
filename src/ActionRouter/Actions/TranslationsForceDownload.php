<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class TranslationsForceDownload extends BaseAction {

	public const SLUG = 'translations_force_download';

	protected function exec() {
		self::con()->comps->translation_downloads->processQueue( true );

		$this->response()->action_response_data = [
			'success'     => true,
			'page_reload' => true,
			'message'     => __( 'Translations have been downloaded.', 'wp-simple-firewall' ),
		];
	}
}
