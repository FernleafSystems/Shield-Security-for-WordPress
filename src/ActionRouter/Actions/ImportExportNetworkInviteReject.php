<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class ImportExportNetworkInviteReject extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_network_invite_reject';

	protected function exec() {
		$form = $this->formData();
		( new NetworkInviteRepository() )->clear( (string)( $form[ 'invite_id' ] ?? '' ) );

		$this->response()->setPayload( [
			'message'      => __( 'Network invite rejected.', 'wp-simple-firewall' ),
			'page_reload'  => true,
			'redirect_url' => $this->importExportUrl(),
		] )->setPayloadSuccess( true );
	}

	private function formData() :array {
		$form = $this->action_data[ 'form_params' ] ?? [];
		return \is_array( $form ) ? $form : [];
	}

	private function importExportUrl() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT );
	}
}
