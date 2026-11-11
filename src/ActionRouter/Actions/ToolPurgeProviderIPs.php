<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Services\Services;

class ToolPurgeProviderIPs extends BaseAction {

	public const SLUG = 'tool_purge_provider_ips';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		Services::ServiceProviders()->clearProviders();
		$this->response()->setPayload( [
			'message' => 'Providers Cleared.'
		] )->setPayloadSuccess( true );
	}
}
