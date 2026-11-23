<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Profiles\ProfileRepository;

class ImportExportProfileOptionsIncludeToggle extends BaseAction {

	public const SLUG = 'importexport_profile_options_include_toggle';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$status = $this->fields()->string( 'status' );
		$keys = $this->optionKeys();
		$success = false;

		if ( self::con()->isPluginAdmin()
			 && \in_array( $status, [ 'include', 'exclude' ], true ) ) {
			$repo = new ProfileRepository();
			$profile = $repo->defaultProfile();
			if ( $profile instanceof ProfileRecord ) {
				$success = $repo->setOptionsIncluded( $profile, $keys, $status === 'include' );
			}
		}

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $success
				? __( 'Import/Export profile options updated successfully.', 'wp-simple-firewall' )
				: __( 'Failed to update Import/Export profile options.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( $success );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'keys',
			'status',
		];
	}

	/**
	 * @return string[]
	 */
	private function optionKeys() :array {
		// A list of keys, or the same keys comma-separated.
		$keys = \is_array( $this->action_data[ 'keys' ] )
			? $this->fields()->stringList( 'keys' )
			: \explode( ',', $this->fields()->string( 'keys' ) );

		return \array_values( \array_filter(
			\array_map( '\trim', $keys ),
			static fn( string $key ) :bool => $key !== ''
		) );
	}
}
