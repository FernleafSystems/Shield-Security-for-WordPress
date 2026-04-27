<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ScanResultVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Base,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ResultsSetTest extends BaseUnitTest {

	public function test_items_for_slug_only_returns_wpv_result_items() :void {
		$wpvMatch = $this->buildWpvItem( 'akismet/akismet.php' );
		$wpvOther = $this->buildWpvItem( 'hello-dolly/hello.php' );
		$baseMatch = $this->buildBaseItem( 'akismet/akismet.php' );

		$set = new Wpv\ResultsSet();
		$set->setItems( [
			$wpvMatch,
			$wpvOther,
			$baseMatch,
		] );

		$this->assertSame( [ $wpvMatch ], $set->getItemsForSlug( 'akismet/akismet.php' ) );
		$this->assertSame( [ 'akismet/akismet.php', 'hello-dolly/hello.php' ], $set->getUniqueSlugs() );
	}

	private function buildWpvItem( string $itemID ) :Wpv\ResultItem {
		$item = new Wpv\ResultItem();
		$item->VO = $this->buildScanResultVO( $itemID );
		return $item;
	}

	private function buildBaseItem( string $itemID ) :Base\ResultItem {
		$item = new Base\ResultItem();
		$item->VO = $this->buildScanResultVO( $itemID );
		return $item;
	}

	private function buildScanResultVO( string $itemID ) :ScanResultVO {
		$vo = new ScanResultVO();
		$vo->item_id = $itemID;
		return $vo;
	}
}
