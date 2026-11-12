<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScanResultsTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\PluginScanner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AbandonedPluginIgnoreIntegrationTest extends ShieldIntegrationTestCase {


	private const SLUG = 'fixture-abandoned/fixture-abandoned.php';
	private const LAST_UPDATED_AT = 1500000000;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_ignoring_removes_a_result_from_every_count_and_stopping_restores_it() :void {
		$this->runApcScan( $this->finding() );
		$id = $this->rows()[ 0 ][ 'id' ];
		$this->assertCounts( 1 );

		$this->assertTrue( $this->action( 'ignore', $id )[ 'success' ] ?? false );
		$this->assertGreaterThan( 0, $this->rows()[ 0 ][ 'ignored_at' ] );
		$this->assertCounts( 0 );

		$this->assertTrue( $this->action( 'unignore', $id )[ 'success' ] ?? false );
		$this->assertSame( 0, $this->rows()[ 0 ][ 'ignored_at' ] );
		$this->assertCounts( 1 );
	}

	public function test_ignore_survives_a_rescan_that_still_finds_the_plugin_abandoned() :void {
		$this->runApcScan( $this->finding() );
		$id = $this->rows()[ 0 ][ 'id' ];
		$this->action( 'ignore', $id );

		$this->runApcScan( $this->finding() );

		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( $id, $rows[ 0 ][ 'id' ] );
		$this->assertGreaterThan( 0, $rows[ 0 ][ 'ignored_at' ] );
		$this->assertSame( 0, $rows[ 0 ][ 'resolved_at' ] );
		$this->assertCounts( 0 );
	}

	/**
	 * The scanner reads this when WordPress.org gives no answer, so it has to include ignored results and carry
	 * their date. Re-emitting that finding keeps the row, and with it the ignore.
	 */
	public function test_an_ignored_open_finding_can_be_carried_through_an_unanswered_lookup() :void {
		$this->runApcScan( $this->finding() );
		$id = $this->rows()[ 0 ][ 'id' ];
		$this->action( 'ignore', $id );

		self::con()->comps->scans->APC()->resetResultsMemoization();
		$scanner = new class extends PluginScanner {
			public function openFindingLastUpdatedAt( string $pluginFile ) :int {
				return $this->getOpenFindingLastUpdatedAt( $pluginFile );
			}
		};
		$lastUpdatedAt = $scanner->openFindingLastUpdatedAt( self::SLUG );
		$this->assertSame( self::LAST_UPDATED_AT, $lastUpdatedAt );
		$this->assertSame( 0, $scanner->openFindingLastUpdatedAt( 'not-a-finding/not-a-finding.php' ) );

		$this->runApcScan( [ [ 'slug' => self::SLUG, 'is_abandoned' => true, 'last_updated_at' => $lastUpdatedAt ] ] );

		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( $id, $rows[ 0 ][ 'id' ] );
		$this->assertGreaterThan( 0, $rows[ 0 ][ 'ignored_at' ] );
		$this->assertCounts( 0 );
	}

	public function test_an_ignore_ends_once_the_plugin_is_no_longer_abandoned() :void {
		$this->runApcScan( $this->finding() );
		$id = $this->rows()[ 0 ][ 'id' ];
		$this->action( 'ignore', $id );

		$this->runApcScan( [] );
		$this->assertSame( 'clean_rescan', $this->rows()[ 0 ][ 'resolution_reason' ] );

		$this->runApcScan( $this->finding() );

		$rows = $this->rows();
		$this->assertCount( 2, $rows );
		$this->assertSame( 0, \array_values( \array_filter( $rows, static fn( array $row ) :bool => $row[ 'id' ] !== $id ) )[ 0 ][ 'ignored_at' ] );
		$this->assertCounts( 1 );
	}

	public function test_a_known_vulnerability_cannot_be_ignored_even_alongside_an_abandoned_result() :void {
		$this->runApcScan( $this->finding() );
		$abandonedID = $this->rows()[ 0 ][ 'id' ];
		$vulnerableID = TestDataFactory::insertScanResultItemTracked( TestDataFactory::insertCompletedScan( 'wpv' ), [
			'item_id'       => 'fixture-vulnerable/fixture-vulnerable.php',
			'is_vulnerable' => 1,
		] )[ 'result_item_id' ];

		$payload = $this->actionForIDs( 'ignore', [ $abandonedID, $vulnerableID ] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertGreaterThan( 0, $this->rows()[ 0 ][ 'ignored_at' ] );
		$this->assertSame( 0, (int)self::con()->db_con->scan_result_items->getQuerySelector()->byId( $vulnerableID )->ignored_at );
	}

	private function assertCounts( int $expected ) :void {
		$apc = self::con()->comps->scans->APC();
		$apc->resetResultsMemoization();
		$this->assertSame( $expected, $apc->getResultsForDisplay()->countItems() );
		$this->assertSame( $expected, ( new Counts() )->countAbandoned() );
		$this->assertSame( $expected, ( new Counts() )->countDistinctAbandonedAssets() );
	}

	private function runApcScan( array $results ) :void {
		$scanID = TestDataFactory::insertCompletedScan( 'apc' );
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'finished_at' => 0,
			'status'      => 'running',
		] );

		$queueItem = new QueueItemVO();
		$queueItem->scan_id = $scanID;
		$queueItem->scan = 'apc';
		$queueItem->scope_type = 'full';
		$queueItem->scope_key = '';
		$queueItem->meta = [];

		( new Store() )->store( $queueItem, $results );
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
	}

	private function finding() :array {
		return [ [ 'slug' => self::SLUG, 'is_abandoned' => true, 'last_updated_at' => self::LAST_UPDATED_AT ] ];
	}

	/**
	 * @return list<array{id:int,ignored_at:int,resolved_at:int,resolution_reason:string}>
	 */
	private function rows() :array {
		return \array_map(
			static fn( $row ) :array => [
				'id'                => (int)$row->id,
				'ignored_at'        => (int)$row->ignored_at,
				'resolved_at'       => (int)$row->resolved_at,
				'resolution_reason' => (string)$row->resolution_reason,
			],
			\array_values( \array_filter(
				self::con()->db_con->scan_result_items->getQuerySelector()->queryWithResult() ?: [],
				static fn( $row ) :bool => (string)$row->item_id === self::SLUG
			) )
		);
	}

	private function action( string $subAction, int $resultItemID ) :array {
		return $this->actionForIDs( $subAction, [ $resultItemID ] );
	}

	/**
	 * @param list<int> $resultItemIDs
	 */
	private function actionForIDs( string $subAction, array $resultItemIDs ) :array {
		return ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass( ScanResultsTableAction::SLUG, [
			'sub_action' => $subAction,
			'rids'       => $resultItemIDs,
		] );
	}
}
