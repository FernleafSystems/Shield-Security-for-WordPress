<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\RunDecisionsUpdate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	DB\CrowdSecDecisions\LoadCrowdSecRecords,
	Lib\AutoUnblock\AutoUnblockCrowdsec,
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

		( new AutoUnblockCrowdsec() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function getApi() :CrowdSecApi {
		return ( new CrowdSecApi() )->setMod( $this->getMod() );
	}

	public function isIpBlockedOnCrowdSec( string $ip ) :bool {
		return $this->isIpOnCrowdSec( $ip, true );
	}

	public function isIpOnCrowdSec( string $ip, bool $blockedOnly = false ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhCS = $mod->getDbH_CrowdSecDecisions();

		$onCS = false;

		if ( !empty( $ip ) ) {
			$records = ( new LoadCrowdSecRecords() )
				->setMod( $this->getMod() )
				->setIP( $ip )
				->selectAll();

			if ( count( $records ) > 0 ) {

				$theRecord = $records[ 0 ];
				unset( $records[ 0 ] );
				$onCS = !$blockedOnly || $theRecord->auto_unblock_at === 0;

				// Remove any duplicates as we go.
				if ( count( $records ) > 0 ) {
					foreach ( $records as $record ) {
						$dbhCS->getQueryDeleter()->deleteById( $record->id );
					}
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