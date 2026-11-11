<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class PluginImportExport_UpdateNotified extends PluginImportExport_Base {

	public const SLUG = 'importexport_updatenotified';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		self::con()->comps->import_export->runOptionsUpdateNotified(
			(string)( $this->action_data[ 'master_url' ] ?? '' ),
			(string)( $this->action_data[ 'id' ] ?? '' )
		);
	}
}
