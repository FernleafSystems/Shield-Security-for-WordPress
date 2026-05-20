<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts\EmailReportInfo;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewReports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\FullPageDisplay\DisplayReport,
	Actions\FullPageDisplay\DisplayReportAdmin,
	Exceptions\UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\BuildReportEmailFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DisplayReportAccessIntegrationTest extends ShieldIntegrationTestCase {

	use BuildReportEmailFixture;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
	}

	public function test_legacy_report_link_redirects_to_admin_report_view() :void {
		$report = $this->insertReport( 'private-report-marker' );

		$action = new DisplayReport( [
			'report_unique_id' => $report[ 'unique_id' ],
		] );
		$action->process();
		$payload = $action->response()->payload();
		$query = $this->queryParamsFromUrl( (string)( $payload[ 'next_step' ][ 'url' ] ?? '' ) );

		$this->assertSame( 'redirect', $payload[ 'next_step' ][ 'type' ] ?? null );
		$this->assertSame( ActionData::FIELD_SHIELD, $query[ ActionData::FIELD_ACTION ] ?? null );
		$this->assertSame( DisplayReportAdmin::SLUG, $query[ ActionData::FIELD_EXECUTE ] ?? null );
		$this->assertSame( $report[ 'unique_id' ], $query[ 'report_unique_id' ] ?? null );
		$this->assertArrayNotHasKey( 'render_output', $payload );
	}

	public function test_legacy_report_link_without_valid_uuid_redirects_to_reports_home() :void {
		$action = new DisplayReport( [
			'report_unique_id' => 'not-a-uuid',
		] );
		$action->process();

		$this->assertSame(
			self::con()->plugin_urls->reportsHome(),
			$action->response()->payload()[ 'next_step' ][ 'url' ] ?? null
		);
	}

	public function test_admin_report_view_requires_authenticated_plugin_admin_user() :void {
		$report = $this->insertReport( 'private-report-marker' );
		\wp_set_current_user( 0 );

		$this->expectException( UserAuthRequiredException::class );

		( new DisplayReportAdminAccessTestDouble( [
			'report_unique_id' => $report[ 'unique_id' ],
		] ) )->process();
	}

	public function test_admin_report_view_rejects_user_without_plugin_admin_capability() :void {
		$report = $this->insertReport( 'private-report-marker' );
		\wp_set_current_user( self::factory()->user->create( [
			'role' => 'subscriber',
		] ) );

		$this->expectException( UserAuthRequiredException::class );

		( new DisplayReportAdminAccessTestDouble( [
			'report_unique_id' => $report[ 'unique_id' ],
		] ) )->process();
	}

	public function test_admin_report_view_renders_report_content_for_authorized_admin_user() :void {
		$report = $this->insertReport( 'private-report-marker' );
		$this->loginAsAdministrator();

		$action = new DisplayReportAdminAccessTestDouble( [
			'report_unique_id' => $report[ 'unique_id' ],
		] );
		$action->process();
		$payload = $action->response()->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertStringContainsString( 'private-report-marker', (string)( $payload[ 'render_output' ] ?? '' ) );
	}

	public function test_report_url_producer_uses_admin_report_view_without_signature() :void {
		$report = $this->insertReport( 'admin-link-marker' );
		$url = self::con()->comps->reports->getReportURL( $report[ 'unique_id' ] );
		$query = $this->queryParamsFromUrl( $url );

		$this->assertStringContainsString( '/wp-admin/', $url );
		$this->assertSame( ActionData::FIELD_SHIELD, $query[ ActionData::FIELD_ACTION ] ?? null );
		$this->assertSame( DisplayReportAdmin::SLUG, $query[ ActionData::FIELD_EXECUTE ] ?? null );
		$this->assertSame( $report[ 'unique_id' ], $query[ 'report_unique_id' ] ?? null );
		$this->assertArrayNotHasKey( 'report_sig', $query );
	}

	public function test_overview_widget_report_producer_uses_admin_report_view_without_signature() :void {
		$report = $this->insertReport( 'widget-link-marker' );
		$data = ( new OverviewReportsAdminLinkTestDouble( [
			'limit' => 1,
		] ) )->renderDataForTest();

		$href = (string)( $data[ 'vars' ][ 'reports' ][ 0 ][ 'href' ] ?? '' );
		$query = $this->queryParamsFromUrl( $href );

		$this->assertSame( DisplayReportAdmin::SLUG, $query[ ActionData::FIELD_EXECUTE ] ?? null );
		$this->assertSame( $report[ 'unique_id' ], $query[ 'report_unique_id' ] ?? null );
		$this->assertArrayNotHasKey( 'report_sig', $query );
	}

	public function test_email_report_producer_uses_admin_report_view_without_signature() :void {
		$report = $this->buildReportFixture( Constants::REPORT_TYPE_INFO );
		$data = ( new EmailReportInfoAdminLinkTestDouble( [
			'home_url'     => 'https://example.com',
			'report'       => $report,
			'detail_level' => 'detailed',
		] ) )->bodyDataForTest();
		$query = $this->queryParamsFromUrl( (string)( $data[ 'vars' ][ 'report' ][ 'href' ] ?? '' ) );

		$this->assertSame( DisplayReportAdmin::SLUG, $query[ ActionData::FIELD_EXECUTE ] ?? null );
		$this->assertSame( $report->record->unique_id, $query[ 'report_unique_id' ] ?? null );
		$this->assertArrayNotHasKey( 'report_sig', $query );
	}

	private function queryParamsFromUrl( string $url ) :array {
		$query = [];
		\parse_str( (string)( \wp_parse_url( $url, \PHP_URL_QUERY ) ?? '' ), $query );
		return $query;
	}

	private function insertReport( string $marker ) :array {
		$dbh = self::con()->db_con->reports;
		$record = $dbh->getRecord();
		$record->type = Constants::REPORT_TYPE_INFO;
		$record->interval_length = 'daily';
		$record->unique_id = \wp_generate_uuid4();
		$record->title = 'Private Report';
		$record->content = \gzdeflate( '<html><body>'.$marker.'</body></html>' );
		$record->protected = false;
		$record->interval_start_at = 100;
		$record->interval_end_at = 200;
		$record->created_at = \time();

		$dbh->getQueryInserter()->insert( $record );
		$inserted = $dbh->getQuerySelector()->filterByReportID( $record->unique_id )->first();

		return [
			'id'        => (int)$inserted->id,
			'unique_id' => $record->unique_id,
		];
	}
}

class DisplayReportAdminAccessTestDouble extends DisplayReportAdmin {

	protected function postExec() {
	}
}

class OverviewReportsAdminLinkTestDouble extends OverviewReports {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}

class EmailReportInfoAdminLinkTestDouble extends EmailReportInfo {

	public function bodyDataForTest() :array {
		return $this->getBodyData();
	}
}
