<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return Services::Request()->isPost()
			   && $this->getCon()->this_req->is_ip_crowdsec_blocked && $opts->isEnabledCrowdSecAutoVisitorUnblock();
	}

	protected function run() {
		try {
			$unblocked = $this->processAutoUnblockRequest();
			if ( $unblocked ) {
				Services::Response()->redirectToHome();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	private function processAutoUnblockRequest() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$unblocked = false;

		$ip = $req->ip();
		if ( empty( $ip ) ) {
			throw new \Exception( 'No IP' );
		}

		if ( $req->post( 'action' ) == $mod->getCon()->prefix() && $req->post( 'exec' ) == 'uau-cs-'.$ip ) {

			if ( !$opts->getCanIpRequestAutoUnblock( $ip ) ) {
				throw new \Exception( 'IP already processed in the last 1hr' );
			}

			// mark IP as having used up it's autounblock option.
			$existing = $opts->getAutoUnblockIps();
			$existing[ $ip ] = Services::Request()->ts();
			$opts->setOpt( 'autounblock_ips', $existing );

			if ( $req->post( '_confirm' ) !== 'Y' ) {
				throw new \Exception( 'No confirmation checkbox.' );
			}
			if ( !empty( $req->post( 'email' ) ) || !empty( $req->post( 'name' ) ) ) {
				throw new \Exception( 'Oh so yummy honey.' );
			}
			if ( wp_verify_nonce( $req->post( 'exec_nonce' ), 'uau-cs-'.$ip ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}

			$csRecord = ( new IPs\DB\CrowdSec\LoadCrowdSecRecords() )
				->setMod( $mod )
				->setIP( $ip )
				->loadRecord();
			if ( !empty( $csRecord ) ) {
				$mod->getDbH_CrowdSec()
					->getQueryUpdater()
					->updateById( $csRecord->id, [
						'auto_unblock_at' => $req->ts()
					] );
				$unblocked = true;
			}
		}

		return $unblocked;
	}
}