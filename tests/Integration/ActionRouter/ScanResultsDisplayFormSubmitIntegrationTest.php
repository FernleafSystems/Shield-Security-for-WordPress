<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScanResultsDisplayFormSubmit;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanResultsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ScanResultsDisplayFormSubmitIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator( [
			'user_login' => 'scan_results_display_submit_tester_'.\uniqid(),
		] );
		$this->setSecurityAdminContext( true );
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'scan_results_table_display',
		] );
		$this->requireController()->opts
			 ->optSet( 'scan_results_table_display', [] )
			 ->store();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_default_submit_requests_page_reload_when_display_options_change() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ScanResultsDisplayFormSubmit::SLUG, [
			'form_data' => [
				'include_ignored'  => 'N',
				'include_repaired' => 'Y',
				'include_deleted'  => 'Y',
			],
		] );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'page_reload' ] ?? false ) );
		$this->assertStoredDisplayOptions(
			[
				'include_deleted',
				'include_repaired',
			],
			$this->requireController()->opts->optGet( 'scan_results_table_display' )
		);
	}

	public function test_actions_queue_submit_suppresses_page_reload_but_keeps_sorted_option_storage() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ScanResultsDisplayFormSubmit::SLUG, [
			'display_context' => ActionsQueueScanResultsOptions::DISPLAY_CONTEXT,
			'form_data'       => [
				'include_ignored'  => 'Y',
				'include_repaired' => 'N',
				'include_deleted'  => 'Y',
			],
		] );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertFalse( (bool)( $payload[ 'page_reload' ] ?? true ) );
		$this->assertStoredDisplayOptions(
			[
				'include_deleted',
				'include_ignored',
			],
			$this->requireController()->opts->optGet( 'scan_results_table_display' )
		);
	}

	public function test_invalid_submit_is_rejected_without_changing_stored_options_or_requesting_reload() :void {
		$this->requireController()->opts
			 ->optSet( 'scan_results_table_display', [ 'include_repaired' ] )
			 ->store();

		$payload = $this->processActionPayloadWithAdminBypass( ScanResultsDisplayFormSubmit::SLUG, [
			'display_context' => ActionsQueueScanResultsOptions::DISPLAY_CONTEXT,
			'form_data'       => [],
		] );

		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
		$this->assertFalse( (bool)( $payload[ 'page_reload' ] ?? true ) );
		$this->assertIsString( $payload[ 'message' ] ?? null );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
		$this->assertStoredDisplayOptions(
			[
				'include_repaired',
			],
			$this->requireController()->opts->optGet( 'scan_results_table_display' )
		);
	}

	private function assertStoredDisplayOptions( array $expectedValues, array $storedOptions ) :void {
		$this->assertSame( $expectedValues, \array_values( $storedOptions ) );
		$this->assertCount( \count( $expectedValues ), $storedOptions );
		$this->assertSame(
			\count( $expectedValues ),
			\count( \array_filter( \array_keys( $storedOptions ), 'is_int' ) )
		);
	}
}
