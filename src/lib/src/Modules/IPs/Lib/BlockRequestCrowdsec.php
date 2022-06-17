<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages\RenderBlockIpCrowdSec;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\{
	CrowdSecRecord,
	LoadCrowdsecDecisions,
	Ops as CrowdsecDecisionsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class BlockRequestCrowdsec extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->getCon()->this_req->is_ip_crowdsec_blocked;
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var CrowdSecRecord[] $records */
		$records = ( new LoadCrowdsecDecisions() )
			->setMod( $mod )
			->setIP( $this->getCon()->this_req->ip )
			->select();
		if ( count( $records ) === 1 ) {
			$theRecord = array_shift( $records );
			/** @var CrowdsecDecisionsDB\Update $updater */
			$updater = $mod->getDbH_CrowdSecDecisions()->getQueryUpdater();
			$updater->setUpdateId( $theRecord->id )
					->updateLastAccessAt();
		}

		$this->getCon()->fireEvent( 'conn_kill_crowdsec' );

		( new RenderBlockIpCrowdSec() )
			->setMod( $this->getMod() )
			->display();
	}
}