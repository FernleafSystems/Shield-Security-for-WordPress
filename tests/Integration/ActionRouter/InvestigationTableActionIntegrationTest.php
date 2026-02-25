<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Investigation\InvestigationTableContract,
	Actions\InvestigationTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigationTableActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function tableDataFixture() :array {
		return [
			'search'  => [ 'value' => '' ],
			'start'   => 0,
			'length'  => 10,
			'order'   => [],
			'columns' => [],
		];
	}

	public function testValidSessionsPayloadReturnsDatatableEnvelope() {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );

		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $userId,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'recordsTotal', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'recordsFiltered', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'searchPanes', $payload[ 'datatable_data' ] );
	}

	public function testUnsupportedTableTypeReturnsFailurePayload() {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => 'unknown',
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 1,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'unsupported_table_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testUnsupportedSubjectTypeForTableReturnsFailurePayload() {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_IP,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => '1.2.3.4',
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'unsupported_subject_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testMissingRequiredKeysReturnsFailurePayloadWithErrorCode() {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'missing_required_action_data', $payload[ 'error_code' ] ?? '' );
	}

	public function testValidActivityPluginPayloadReturnsDatatableEnvelope() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $this->firstInstalledPluginSlug(),
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	public function testValidActivityThemePayloadReturnsDatatableEnvelope() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_THEME,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $this->firstInstalledThemeSlug(),
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	public function testValidActivityCorePayloadReturnsDatatableEnvelope() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for activity table integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}

	private function firstInstalledThemeSlug() :string {
		$themes = Services::WpThemes()->getInstalledStylesheets();
		if ( empty( $themes ) ) {
			$this->markTestSkipped( 'No installed themes were available for activity table integration test.' );
		}
		return (string)\array_values( $themes )[ 0 ];
	}
}
