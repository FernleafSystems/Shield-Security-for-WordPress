<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\RunDecisionsUpdate;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	ModCon,
	Options
};

class CrowdSecController extends ExecOnceModConsumer {

	use PluginCronsConsumer;

	/**
	 * @var CrowdSecCfg
	 */
	public $cfg;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$this->cfg = ( new CrowdSecCfg() )->applyFromArray( $opts->getOpt( 'crowdsec_cfg' ) );
		$this->setupCronHooks();
	}

	public function isIpOnCrowdSec( string $ip ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhCS = $mod->getDbH_CrowdSec();

		$records = Services::WpDb()->selectCustom( sprintf(
			"SELECT `ips`.`id`
				FROM `%s` as `ips`
				INNER JOIN `%s` as `cs` ON `ips`.`id` = `cs`.`ip_ref`
				WHERE `ips`.`ip`=INET6_ATON('%s');",
			$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
			$dbhCS->getTableSchema()->table,
			$ip
		) );

		$onCS = false;
		if ( is_array( $records ) && count( $records ) > 0 ) {
			$onCS = true;
			// Remove any duplicates as we go.
			if ( count( $records ) > 1 ) {
				array_shift( $records );
				foreach ( $records as $record ) {
					$dbhCS->getQueryDeleter()->deleteById( $record[ 'id' ] );
				}
			}
		}

		return $onCS;
	}

	public function runDailyCron() {
		( new RunDecisionsUpdate() )
			->setMod( $this->getMod() )
			->execute();
	}
}