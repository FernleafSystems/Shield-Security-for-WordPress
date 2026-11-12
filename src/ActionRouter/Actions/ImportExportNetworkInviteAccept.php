<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics\ObservationPresenter;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class ImportExportNetworkInviteAccept extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_network_invite_accept';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$repo = new NetworkInviteRepository();
		$import = null;
		$importCompleted = false;
		try {
			$form = $this->formParams();
			if ( ( $form[ 'confirm' ] ?? '' ) !== 'Y' ) {
				throw new \RuntimeException( __( 'Please check the box to confirm this action.', 'wp-simple-firewall' ) );
			}
			if ( !self::con()->comps->import_export->isSyncEnabled() ) {
				throw new \RuntimeException( __( 'Import and export is not enabled.', 'wp-simple-firewall' ) );
			}
			if ( !$repo->canReviewInvites() ) {
				throw new \RuntimeException( __( 'Network invite is no longer available.', 'wp-simple-firewall' ) );
			}

			$invite = $repo->find( (string)( $form[ 'invite_id' ] ?? '' ) );
			if ( $invite === null ) {
				throw new \RuntimeException( __( 'Network invite was not found.', 'wp-simple-firewall' ) );
			}

			$import = new Import();
			$import->fromSite( $invite[ 'master_url' ], '', true, Import::REQUEST_SAFETY_TRUSTED_SYNC );
			$importCompleted = true;
			$repo->clear( $invite[ 'id' ] );
			$success = true;
			$message = __( 'Network invite accepted.', 'wp-simple-firewall' );
		}
		catch ( \Throwable $e ) {
			$success = false;
			$message = $import instanceof Import && !$importCompleted
				? ( new ObservationPresenter() )->failureMessage( $import->latestObservation(), $e->getMessage() )
				: $e->getMessage();
		}

		$this->response()->setPayload( [
			'message'      => $message,
			'page_reload'  => $success,
			'redirect_url' => $success ? $this->importExportUrl() : '',
		] )->setPayloadSuccess( $success );
	}

	private function importExportUrl() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT );
	}
}
