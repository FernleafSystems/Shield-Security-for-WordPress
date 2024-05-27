<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class ToolPurgeProviderIPs extends BaseAction {

	use Traits\NonceVerifyRequired;

	public const SLUG = 'tool_purge_provider_ips';

	protected function exec() {
		Services::ServiceProviders()->clearProviders();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => 'Providers Cleared.'
		];
	}
}