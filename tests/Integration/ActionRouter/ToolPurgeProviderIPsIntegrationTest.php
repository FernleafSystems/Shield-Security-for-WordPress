<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ToolPurgeProviderIPs
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class ToolPurgeProviderIPsIntegrationTest extends ShieldIntegrationTestCase {

	private array $servicesSnapshot = [];

	private ServiceProviders $serviceProviders;

	public function set_up() {
		parent::set_up();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->serviceProviders = new class extends ServiceProviders {
			public int $clears = 0;

			public function clearProviders() :void {
				$this->clears++;
			}
		};
		ServicesState::mergeItems( [
			'service_serviceproviders' => $this->serviceProviders,
		] );

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = true;
	}

	public function tear_down() {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tear_down();
	}

	public function test_provider_purge_action_clears_service_provider_cache_once() :void {
		$payload = ( new ActionProcessor() )->processAction(
			ToolPurgeProviderIPs::SLUG,
			ActionData::Build( ToolPurgeProviderIPs::class )
		)->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( 1, $this->serviceProviders->clears );
	}
}
