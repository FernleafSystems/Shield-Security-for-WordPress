<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;

class ImportExportProfileOptionsIncludeToggle extends BaseAction {

	public const SLUG = 'importexport_profile_options_include_toggle';

	protected function exec() {
		$status = (string)$this->action_data[ 'status' ];
		$success = false;

		if ( self::con()->isPluginAdmin()
			 && \in_array( $status, [ 'include', 'exclude' ], true ) ) {
			$repo = new ProfileRepository();
			$profile = $repo->defaultProfile();
			if ( $profile instanceof ProfileRecord ) {
				$success = $repo->setOptionsIncluded( $profile, $this->optionKeys(), $status === 'include' );
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
		$keys = $this->action_data[ 'keys' ];
		if ( \is_array( $keys ) ) {
			$keys = \array_map( '\strval', $keys );
		}
		else {
			$keys = \explode( ',', (string)$keys );
		}

		return \array_values( \array_filter(
			\array_map( '\trim', $keys ),
			static fn( string $key ) :bool => $key !== ''
		) );
	}
}
