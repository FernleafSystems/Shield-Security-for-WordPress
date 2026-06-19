<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\ResolveSubmittedOptionValues;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class ImportExportProfileOptionsSave extends BaseAction {

	public const SLUG = 'importexport_profile_options_save';

	protected function exec() {
		$success = false;

		try {
			if ( !self::con()->isPluginAdmin() ) {
				throw new \Exception();
			}

			$repo = new ProfileRepository();
			$profile = $repo->primaryProfile();
			if ( !( $profile instanceof ProfileRecord ) ) {
				throw new \Exception();
			}

			$form = FormParams::Retrieve();
			if ( empty( $form[ 'all_opts_keys' ] ) ) {
				throw new \Exception();
			}

			$resolved = ( new ResolveSubmittedOptionValues() )->resolve( $form );
			$success = $repo->saveOptionValues( $profile, $resolved[ 'values' ] );
		}
		catch ( \Throwable $e ) {
			$success = false;
		}

		$this->response()->setPayload( [
			'html'        => '',
			'page_reload' => false,
			'message'     => $success
				? __( 'Import/Export profile updated successfully.', 'wp-simple-firewall' )
				: __( 'Failed to update Import/Export profile.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( $success );
	}
}
