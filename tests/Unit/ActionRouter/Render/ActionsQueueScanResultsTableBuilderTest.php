<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanResultsTableBuilder,
	ScanResultsDisplayOptions,
	ScanResultsTableContractBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueScanResultsTableBuilderTest extends BaseUnitTest {

	public function testWordpressQueueTableAlwaysBuildsTableContract() :void {
		$table = $this->newBuilder()->buildWordpressTable();

		$this->assertSame( 'file_status', $table[ 'contract' ] ?? '' );
		$this->assertFalse( (bool)( $table[ 'is_empty' ] ?? true ) );
		$this->assertSame(
			( new ScanResultsDisplayOptions() )->activeOnly(),
			$table[ 'action_data' ][ 'results_display_options' ] ?? []
		);
	}

	public function testMalwareQueueTableAlwaysBuildsTableContract() :void {
		$table = $this->newBuilder()->buildMalwareTable();

		$this->assertSame( 'malware', $table[ 'contract' ] ?? '' );
		$this->assertFalse( (bool)( $table[ 'is_empty' ] ?? true ) );
		$this->assertSame(
			( new ScanResultsDisplayOptions() )->activeOnly(),
			$table[ 'action_data' ][ 'results_display_options' ] ?? []
		);
	}

	private function newBuilder() :ActionsQueueScanResultsTableBuilder {
		return new class(
			null,
			null,
			new class extends ScanResultsTableContractBuilder {
				public function buildFileStatus(
					string $subjectType,
					string $subjectId,
					string $fullLogHref,
					array $scanResultsActionData = []
				) :array {
					return [
						'contract'    => 'file_status',
						'is_empty'    => false,
						'action_data' => $scanResultsActionData,
					];
				}

				public function buildMalware( string $fullLogHref, array $scanResultsActionData = [] ) :array {
					return [
						'contract'    => 'malware',
						'is_empty'    => false,
						'action_data' => $scanResultsActionData,
					];
				}
			}
		) extends ActionsQueueScanResultsTableBuilder {
			protected function buildFullLogHref() :string {
				return '/queue/scans';
			}
		};
	}
}
