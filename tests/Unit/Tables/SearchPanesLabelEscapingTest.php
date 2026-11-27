<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SearchPanesLabelEscapingTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		// Like WordPress, this encodes quotes and leaves existing entities alone.
		Functions\when( 'esc_html' )->alias(
			static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES, 'UTF-8', false )
		);
	}

	private function createBuilder( array $searchPanesData ) :object {
		return new class( $searchPanesData ) extends BaseBuildTableData {

			private array $searchPanesData;

			public function __construct( array $searchPanesData ) {
				$this->searchPanesData = $searchPanesData;
				$this->table_data = [
					'search'      => [ 'value' => '' ],
					'searchPanes' => [],
					'start'       => 0,
					'length'      => 10,
					'order'       => [],
					'columns'     => [],
				];
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

			protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData {
				throw new \RuntimeException( 'Not implemented' );
			}

			protected function buildWheresFromSearchParams() :array {
				return [];
			}

			protected function getSearchPanesData() :array {
				return $this->searchPanesData;
			}

			public function testBuild() :array {
				return $this->build();
			}
		};
	}

	public function test_option_labels_are_escaped_and_values_kept() :void {
		$hostile = '"><img src=x onerror=alert(1)>';

		$result = $this->createBuilder( [
			'options' => [
				'status' => [
					[ 'label' => $hostile, 'value' => 'status_a' ],
					[ 'label' => 'Plain & simple', 'value' => 'status_b' ],
				],
				'ext'    => [
					[ 'label' => $hostile, 'value' => $hostile ],
				],
			],
		] )->testBuild();

		$options = $result[ 'searchPanes' ][ 'options' ];
		$this->assertSame( \htmlspecialchars( $hostile, \ENT_QUOTES ), $options[ 'status' ][ 0 ][ 'label' ] );
		$this->assertSame( 'Plain &amp; simple', $options[ 'status' ][ 1 ][ 'label' ] );
		$this->assertSame( \htmlspecialchars( $hostile, \ENT_QUOTES ), $options[ 'ext' ][ 0 ][ 'label' ] );

		// The value is what filtering matches on, so it reaches the browser unchanged.
		$this->assertSame( 'status_a', $options[ 'status' ][ 0 ][ 'value' ] );
		$this->assertSame( $hostile, $options[ 'ext' ][ 0 ][ 'value' ] );
	}

	public function test_data_without_options_is_returned_unchanged() :void {
		$this->assertSame( [], $this->createBuilder( [] )->testBuild()[ 'searchPanes' ] );
	}
}
