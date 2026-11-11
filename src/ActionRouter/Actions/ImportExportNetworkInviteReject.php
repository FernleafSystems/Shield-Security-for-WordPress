<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class ImportExportNetworkInviteReject extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_network_invite_reject';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$form = $this->formParams();
		( new NetworkInviteRepository() )->reject( (string)( $form[ 'invite_id' ] ?? '' ) );

		$this->response()->setPayload( [
			'message'      => __( 'Network invite rejected.', 'wp-simple-firewall' ),
			'page_reload'  => true,
			'redirect_url' => $this->importExportUrl(),
		] )->setPayloadSuccess( true );
	}

	private function importExportUrl() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT );
	}
}
