<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Scans;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LoadFileScanResultsTableDataEscapingTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_html' )->alias(
			static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES, 'UTF-8' )
		);
		Functions\when( 'esc_attr' )->alias(
			static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES, 'UTF-8' )
		);
	}

	public function test_file_type_label_escapes_extension_for_row_data() :void {
		$loader = $this->newInspectableLoader();
		$item = $this->newItemWithPath( 'C:/tmp/payload.<script>alert(1)</script>' );

		$this->assertSame(
			'&lt;SCRIPT&gt;ALERT(1)&lt;/SCRIPT&gt;',
			$loader->fileTypeLabel( $item )
		);
		$this->assertSame(
			'&lt;SCRIPT&gt;ALERT(1)&lt;/SCRIPT&gt;',
			$loader->statusFileType( $item )
		);
	}

	private function newInspectableLoader() :LoadFileScanResultsTableData {
		return new class extends LoadFileScanResultsTableData {
			public function fileTypeLabel( ResultItem $item ) :string {
				return $this->column_fileTypeLabel( $item );
			}

			public function statusFileType( ResultItem $item ) :string {
				return $this->column_fileType( $item );
			}
		};
	}

	private function newItemWithPath( string $path ) :ResultItem {
		$item = new ResultItem();
		$item->path_full = $path;
		return $item;
	}
}
