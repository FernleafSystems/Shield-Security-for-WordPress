<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Export;

class PluginImportExport_Export extends PluginImportExport_Base {

	public const SLUG = 'importexport_export';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		( new Export() )->run( (string)( $this->action_data[ 'method' ] ?? '' ), $this->action_data );
	}
}
