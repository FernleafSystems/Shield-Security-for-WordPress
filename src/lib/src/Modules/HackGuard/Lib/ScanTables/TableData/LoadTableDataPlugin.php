<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class LoadTableDataPlugin extends BaseLoadTableDataPluginTheme {

	/**
	 * @var WpPluginVo
	 */
	private $plugin;

	public function __construct( WpPluginVo $plugin ) {
		$this->plugin = $plugin;
	}

	protected function getRecordRetriever() :RetrieveItems {
		$ret = parent::getRecordRetriever();
		return $ret->addWheres( [
			sprintf( "%s.`meta_key`='ptg_slug'", $ret::ABBR_RESULTITEMMETA ),
			sprintf( "%s.`meta_value`='%s'", $ret::ABBR_RESULTITEMMETA, $this->plugin->file ),
		] );
	}
}