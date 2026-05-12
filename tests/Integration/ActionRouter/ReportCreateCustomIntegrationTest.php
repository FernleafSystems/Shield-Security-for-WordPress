<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ReportCreateCustom,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportCreateCustomIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_create_custom_report_requires_valid_nonce_before_report_creation() :void {
		$before = $this->countReports();
		$snapshot = $this->seedActionNonceContext( ReportCreateCustom::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => '',
		] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			$this->processor()->processAction( ReportCreateCustom::SLUG, [] );
		}
		finally {
			$this->assertSame( $before, $this->countReports() );
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_create_custom_report_normalizes_malformed_area_inputs_before_generation() :void {
		$before = $this->countReports();
		$this->requireController()->this_req->wp_is_ajax = true;
		$this->mergeCurrentRequestTransport( [
			'form_params' => [
				'title'            => 'Malformed Area Inputs',
				'start_date'       => '2026-01-01',
				'end_date'         => '2026-01-02',
				'changes_zones'    => 'plugins',
				'statistics_zones' => 'security',
				'scans_zones'      => 'scan_results',
			],
		] );

		$payload = $this->processor()->processAction(
			ReportCreateCustom::SLUG,
			ActionData::Build( ReportCreateCustom::class, true )
		)->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( $before, $this->countReports() );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function countReports() :int {
		return self::con()->db_con->reports->getQuerySelector()->count();
	}
}
