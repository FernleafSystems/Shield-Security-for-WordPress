<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Services\Services;

class PluginImportExport_NetworkInviteRequest extends PluginImportExport_Base {

	public const SLUG = 'importexport_network_invite_request';

	protected function exec() {
		if ( \strtoupper( (string)Services::Request()->server( 'REQUEST_METHOD', '' ) ) === 'POST' ) {
			( new NetworkInviteRepository() )->receive( (string)Services::Request()->post( 'master_url', '' ) );
		}

		$this->response()->setPayload();
	}
}
