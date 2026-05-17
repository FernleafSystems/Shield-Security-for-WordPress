<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\LicenseClear
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class LicenseClearIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();

		try {
			$this->applyCurrentShieldAjaxRequest( ActionData::Build( LicenseClear::class ), true );
			$this->startLocalEmailCapture();
		}
		catch ( \Throwable $throwable ) {
			try {
				$this->stopLocalEmailCapture();
			}
			finally {
				$this->restoreCurrentRequestState( $this->requestSnapshot );
			}
			throw $throwable;
		}
	}

	public function tear_down() {
		try {
			$this->stopLocalEmailCapture();
		}
		finally {
			try {
				if ( $this->requestSnapshot !== [] ) {
					$this->restoreCurrentRequestState( $this->requestSnapshot );
				}
			}
			finally {
				parent::tear_down();
			}
		}
	}

	public function test_license_clear_deactivates_active_pro_state_without_email() :void {
		$this->enablePremiumCapabilities( [
			'reports_local',
			'site_blockdown',
			'whitelabel',
		] );
		$con = $this->requireController();
		$activatedAt = (int)$con->opts->optGet( 'license_activated_at' );
		$this->captureShieldEvents();

		$payload = $this->processLicenseClear();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'page_reload' ] ?? false ) );
		$this->assertSame( [], $con->opts->optGet( 'license_data' ) );
		$this->assertGreaterThanOrEqual( $activatedAt, (int)$con->opts->optGet( 'license_deactivated_at' ) );
		$this->assertFalse( $con->comps->license->isActive() );
		$this->assertFalse( $con->comps->license->hasValidWorkingLicense() );
		$this->assertFalse( $con->isPremiumActive() );
		$this->assertFalse( $con->caps->canReportsLocal() );
		$this->assertFalse( $con->caps->canSiteBlockdown() );
		$this->assertFalse( $con->caps->canWhitelabel() );
		$this->assertSame( [], $this->capturedMails() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'lic_fail_deactivate' ) );
	}

	public function test_license_clear_is_idempotent_when_license_is_already_inactive() :void {
		$con = $this->requireController();
		$this->disablePremiumCapabilities();
		$deactivatedAt = (int)$con->opts->optGet( 'license_deactivated_at' );
		$this->captureShieldEvents();

		$payload = $this->processLicenseClear();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'page_reload' ] ?? false ) );
		$this->assertSame( [], $con->opts->optGet( 'license_data' ) );
		$this->assertSame( $deactivatedAt, (int)$con->opts->optGet( 'license_deactivated_at' ) );
		$this->assertFalse( $con->comps->license->isActive() );
		$this->assertFalse( $con->comps->license->hasValidWorkingLicense() );
		$this->assertSame( [], $this->capturedMails() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'lic_fail_deactivate' ) );
	}

	private function processLicenseClear() :array {
		return ( new ActionProcessor() )->processAction(
			LicenseClear::SLUG,
			ActionData::Build( LicenseClear::class )
		)->payload();
	}
}
