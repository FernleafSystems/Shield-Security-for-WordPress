<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\{
	BaseBuildSearchPanesData,
	Investigation\BaseInvestigationData
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BaseInvestigationDataTest extends BaseUnitTest {

	private function createBuilder( array $searchWheres, array $subjectWheres ) :BaseInvestigationData {
		return new class( $searchWheres, $subjectWheres ) extends BaseInvestigationData {

			private array $searchWheres;

			private array $subjectWheres;

			public function __construct( array $searchWheres, array $subjectWheres ) {
				$this->searchWheres = $searchWheres;
				$this->subjectWheres = $subjectWheres;
			}

			protected function countTotalRecords() :int {
				return 0;
			}

			protected function countTotalRecordsFiltered() :int {
				return 0;
			}

			protected function buildTableRowsFromRawRecords( array $records ) :array {
				return [];
			}

			protected function buildWheresFromInvestigationSearch() :array {
				return $this->searchWheres;
			}

			protected function getSubjectWheres() :array {
				return $this->subjectWheres;
			}

			public function exposeBuildWheresFromSearchParams() :array {
				return $this->buildWheresFromSearchParams();
			}

			public function exposeGetSearchPanesDataBuilder() :BaseBuildSearchPanesData {
				return $this->getSearchPanesDataBuilder();
			}

			public function exposeTotalCountCacheKey() :string {
				return $this->getTotalCountCacheKey();
			}
		};
	}

	public function testSubjectWheresAlwaysInjected() :void {
		$builder = $this->createBuilder( [ '`log`.`event_slug`=\'x\'' ], [ '`req`.`uid`=42' ] );
		$wheres = $builder->exposeBuildWheresFromSearchParams();

		$this->assertContains( '`log`.`event_slug`=\'x\'', $wheres );
		$this->assertContains( '`req`.`uid`=42', $wheres );
	}

	public function testSubjectWheresInjectedEvenWhenSearchWheresEmpty() :void {
		$builder = $this->createBuilder( [], [ '`req`.`uid`=42' ] );
		$wheres = $builder->exposeBuildWheresFromSearchParams();

		$this->assertSame( [ '`req`.`uid`=42' ], $wheres );
	}

	public function testSearchPanesBuilderContractRemainsStrict() :void {
		$builder = $this->createBuilder( [], [] );
		$searchPanesBuilder = $builder->exposeGetSearchPanesDataBuilder();

		$this->assertInstanceOf( BaseBuildSearchPanesData::class, $searchPanesBuilder );
	}

	public function testTotalCountCachingDisabledForSubjectScopedBuilders() :void {
		$builder = $this->createBuilder( [], [] );

		$this->assertSame( '', $builder->exposeTotalCountCacheKey() );
	}
}

