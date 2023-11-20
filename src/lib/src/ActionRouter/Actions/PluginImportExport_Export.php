<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

class PluginImportExport_Export extends PluginImportExport_Base {

	public const SLUG = 'importexport_export';

	protected function exec() {
		( new Export() )->run( $this->action_data[ 'method' ] ?? '' );
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}