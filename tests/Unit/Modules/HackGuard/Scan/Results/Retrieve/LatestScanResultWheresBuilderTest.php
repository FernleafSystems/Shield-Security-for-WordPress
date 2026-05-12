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
			"`ri`.`scan`='afs'",
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`=0",
			"`ri`.`resolved_at`=0",
		], $builder->forContext( 'afs', RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ) );

		$this->assertSame( [
			"`ri`.`scan`='afs'",
			"`ri`.`resolved_at`=0",
		], $builder->forLatestResults( 'afs' ) );
	}

	public function test_for_results_display_with_options_supports_queue_owned_ignored_only_filters() :void {
		$builder = new LatestScanResultWheresBuilder();

		$this->assertSame( [
			"`ri`.`scan`='afs'",
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`resolution_reason`!='clean_rescan'",
			"`ri`.`resolution_reason`!='asset_replaced'",
			"`ri`.`ignored_at`=0",
			"(`ri`.`resolved_at`=0 OR `ri`.`resolution_reason`!='repaired')",
			"(`ri`.`resolved_at`=0 OR `ri`.`resolution_reason`!='deleted')",
		], $builder->forResultsDisplayWithOptions( 'afs' ) );

		$this->assertSame( [
			"`ri`.`scan`='afs'",
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`resolution_reason`!='clean_rescan'",
			"`ri`.`resolution_reason`!='asset_replaced'",
			"`ri`.`ignored_at`>0",
			"(`ri`.`resolved_at`=0 OR `ri`.`resolution_reason`!='repaired')",
			"(`ri`.`resolved_at`=0 OR `ri`.`resolution_reason`!='deleted')",
		], $builder->forResultsDisplayWithOptions( 'afs', [
			'include_ignored' => true,
			'ignored_only'    => true,
		] ) );
	}
}
