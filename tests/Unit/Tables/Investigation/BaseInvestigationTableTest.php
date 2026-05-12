<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\BaseInvestigationTable;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class TestSourceTableBuilder extends Base {

	protected function getColumnDefs() :array {
		return [
			'actor'   => [ 'data' => 'actor', 'className' => 'actor' ],
			'subject' => [ 'data' => 'subject', 'className' => 'subject' ],
			'event'   => [ 'data' => 'event', 'className' => 'event' ],
			'date'    => [ 'data' => 'date', 'className' => 'date' ],
		];
	}

	protected function getColumnsToDisplay() :array {
		return [ 'actor', 'subject', 'event', 'date' ];
	}

	protected function getOrderColumnSlug() :string {
		return 'date';
	}
}

class BaseInvestigationTableTest extends BaseUnitTest {

	private function createBuilder() :BaseInvestigationTable {
		return new class extends BaseInvestigationTable {

			protected function getSourceBuilderClass() :string {
				return TestSourceTableBuilder::class;
			}

			protected function getSubjectFilterColumns() :array {
				return $this->subjectType === 'user' ? [ 'subject' ] : [];
			}
		};
	}

	public function testSearchPanesDisabledByDefault() :void {
		$builder = $this->createBuilder();
		$raw = $builder->buildRaw();

		$this->assertArrayHasKey( 'searchPanes', $raw );
		$this->assertSame( [], $raw[ 'searchPanes' ] );
	}

	public function testSubjectColumnsRemovedWhileKeepingSourceOrder() :void {
		$builder = $this->createBuilder();
		$builder->setSubject( 'user', 42 );
		$raw = $builder->buildRaw();

		$this->assertSame(
			[ 'actor', 'event', 'date' ],
			\array_column( $raw[ 'columns' ], 'data' )
		);
	}

	public function testSetSubjectIsFluent() :void {
		$builder = $this->createBuilder();

		$this->assertSame(
			$builder,
			$builder->setSubject( 'ip', '1.2.3.4' )
		);
	}
}
