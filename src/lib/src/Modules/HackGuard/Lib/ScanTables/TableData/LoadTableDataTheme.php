<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class LoadTableDataTheme extends BaseLoadTableDataPluginTheme {

	/**
	 * @var WpThemeVo
	 */
	private $theme;

	public function __construct( WpThemeVo $theme ) {
		$this->theme = $theme;
	}

	protected function getRecordRetriever() :Retrieve {
		$retriever = parent::getRecordRetriever();
		$retriever->wheres = array_merge( [
			"`rim`.`meta_key`='ptg_slug'",
			sprintf( "`rim`.`meta_value`='%s'", $this->theme->stylesheet ),
		], $retriever->wheres ?? [] );
		return $retriever;
	}
}