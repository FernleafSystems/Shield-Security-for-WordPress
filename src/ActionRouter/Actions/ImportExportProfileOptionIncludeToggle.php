<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Profiles\ProfileRepository;

class ImportExportProfileOptionIncludeToggle extends BaseAction {

	public const SLUG = 'importexport_profile_option_include_toggle';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$key = $this->fields()->string( 'key' );
		$status = $this->fields()->string( 'status' );
		$success = false;

		if ( self::con()->isPluginAdmin()
			 && !empty( $key )
			 && \in_array( $status, [ 'include', 'exclude' ], true ) ) {
			$repo = new ProfileRepository();
			$profile = $repo->defaultProfile();
			if ( $profile instanceof ProfileRecord ) {
				$success = $repo->setOptionIncluded( $profile, $key, $status === 'include' );
			}
		}

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $success
				? __( 'Import/Export profile option updated successfully.', 'wp-simple-firewall' )
				: __( 'Failed to update Import/Export profile option.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( $success );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'key',
			'status',
		];
	}
}
