<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class LoadTableDataTheme extends BaseLoadTableDataPluginTheme {

	/**
	 * @var WpThemeVo
	 */
	private $theme;

	public function __construct( WpThemeVo $theme ) {
		$this->theme = $theme;
	}

	protected function getRecordRetriever() :RetrieveItems {
		$ret = parent::getRecordRetriever();
		return $ret->addWheres( [
			sprintf( "%s.`meta_key`='ptg_slug'", $ret::ABBR_RESULTITEMMETA ),
			sprintf( "%s.`meta_value`='%s'", $ret::ABBR_RESULTITEMMETA, $this->theme->stylesheet ),
		] );
	}
}