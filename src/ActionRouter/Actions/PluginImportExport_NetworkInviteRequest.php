<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Services\Services;

class PluginImportExport_NetworkInviteRequest extends PluginImportExport_Base {

	public const SLUG = 'importexport_network_invite_request';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		if ( \strtoupper( (string)Services::Request()->server( 'REQUEST_METHOD', '' ) ) === 'POST' ) {
			( new NetworkInviteRepository() )->receive( $this->formFields()->string( 'master_url', '' ) );
		}

		$this->response()->setPayload();
	}
}
