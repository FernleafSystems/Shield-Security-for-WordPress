<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class ImportExportNetworkInviteAccept extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_network_invite_accept';

	protected function exec() {
		$repo = new NetworkInviteRepository();
		try {
			$form = $this->formData();
			if ( ( $form[ 'confirm' ] ?? '' ) !== 'Y' ) {
				throw new \RuntimeException( __( 'Please check the box to confirm this action.', 'wp-simple-firewall' ) );
			}
			if ( !( new ImportExportController() )->isSyncEnabled() ) {
				throw new \RuntimeException( __( 'Import and export is not enabled.', 'wp-simple-firewall' ) );
			}
			if ( !$repo->canReviewInvites() ) {
				throw new \RuntimeException( __( 'Network invite is no longer available.', 'wp-simple-firewall' ) );
			}

			$invite = $repo->find( (string)( $form[ 'invite_id' ] ?? '' ) );
			if ( $invite === null ) {
				throw new \RuntimeException( __( 'Network invite was not found.', 'wp-simple-firewall' ) );
			}

			( new Import() )->fromSite( (string)$invite[ 'master_url' ], '', true, Import::REQUEST_SAFETY_TRUSTED_SYNC );
			$repo->clear( (string)$invite[ 'id' ] );
			$success = true;
			$message = __( 'Network invite accepted.', 'wp-simple-firewall' );
		}
		catch ( \Throwable $e ) {
			$success = false;
			$message = $e->getMessage();
		}

		$this->response()->setPayload( [
			'message'      => $message,
			'page_reload'  => $success,
			'redirect_url' => $success ? $this->importExportUrl() : '',
		] )->setPayloadSuccess( $success );
	}

	private function formData() :array {
		$form = $this->action_data[ 'form_params' ] ?? [];
		return \is_array( $form ) ? $form : [];
	}

	private function importExportUrl() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT );
	}
}
