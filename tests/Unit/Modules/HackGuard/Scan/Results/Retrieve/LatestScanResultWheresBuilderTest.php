<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	LatestScanResultWheresBuilder,
	RetrieveCount
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	McpTestControllerFactory,
	PluginControllerInstaller
};

class LatestScanResultWheresBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		McpTestControllerFactory::install( [], true );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_for_context_returns_expected_where_clauses() :void {
		$builder = new LatestScanResultWheresBuilder();

		$this->assertSame( [
			"`sr`.`scan_ref`=99",
			"`ri`.`deleted_at`=0",
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`=0",
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
		], $builder->forContext( 99, RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ) );

		$this->assertSame( [
			"`sr`.`scan_ref`=99",
			"`ri`.`deleted_at`=0",
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
		], $builder->forLatestResults( 99 ) );
	}

	public function test_for_results_display_with_options_supports_queue_owned_ignored_only_filters() :void {
		$builder = new LatestScanResultWheresBuilder();

		$this->assertSame( [
			"`sr`.`scan_ref`=99",
			"`ri`.`deleted_at`=0",
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`=0",
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
		], $builder->forResultsDisplayWithOptions( 99 ) );

		$this->assertSame( [
			"`sr`.`scan_ref`=99",
			"`ri`.`deleted_at`=0",
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`>0",
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
		], $builder->forResultsDisplayWithOptions( 99, [
			'include_ignored' => true,
			'ignored_only'    => true,
		] ) );
	}
}
