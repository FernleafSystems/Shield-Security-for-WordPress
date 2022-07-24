<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
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

	protected function getRecordRetriever() :Retrieve {
		$retriever = parent::getRecordRetriever();
		$retriever->wheres = array_merge( [
			"`rim`.`meta_key`='ptg_slug'",
			sprintf( "`rim`.`meta_value`='%s'", $this->plugin->file ),
		], $retriever->wheres ?? [] );
		return $retriever;
	}
}