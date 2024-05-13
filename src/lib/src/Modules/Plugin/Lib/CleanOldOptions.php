<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanOldOptions {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		$DB = Services::WpDb();
		$DB->doSql( sprintf( "DELETE FROM `%s` WHERE `option_name` LIKE 'icwp_wpsf_%%_options' limit 25", $DB->getTable_Options() ) );
		$DB->doSql( sprintf( "DELETE FROM `%s` WHERE `option_name`='icwp_wpsf_plugin_controller'", $DB->getTable_Options() ) );
	}
}