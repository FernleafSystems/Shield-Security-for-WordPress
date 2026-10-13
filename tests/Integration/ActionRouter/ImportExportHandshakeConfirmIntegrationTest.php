<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions\PluginImportExport_HandshakeConfirm
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportHandshakeConfirmIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_handshake_expires_at',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_expired_handshake_confirmation_returns_nonconfirming_payload() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_handshake_expires_at', Services::Request()->ts() - 1 )
			->store();

		$routed = $con->action_router->action(
			PluginImportExport_HandshakeConfirm::class,
			[],
			ActionRoutingController::ACTION_SHIELD
		);

		$this->assertSame( [ 'success' => false ], $routed->payload() );
	}
}
