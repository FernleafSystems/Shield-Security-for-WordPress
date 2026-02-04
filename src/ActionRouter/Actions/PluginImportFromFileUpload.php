<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;

class PluginImportFromFileUpload extends BaseAction {

	public const SLUG = 'import_from_file_upload';

	protected function exec() {
		try {
			( new Import() )->fromFileUpload();
			$success = true;
			$msg = __( 'Options imported successfully', 'wp-simple-firewall' );
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg
		];
	}
}