<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseAutoUnblock extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return Services::Request()->isPost();
	}

	protected function run() {
		try {
			if ( $this->processAutoUnblockRequest() ) {
				Services::Response()->redirectToHome();
			}
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function processAutoUnblockRequest() :bool;

	/**
	 * @throws \Exception
	 */
	protected function canRunUnblock() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();
		$nonceActionID = $this->getNonceAction();

		$canUnblock = false;

		$ip = $req->ip();
		if ( empty( $ip ) ) {
			throw new \Exception( 'No IP' );
		}

		if ( $req->post( 'action' ) == $mod->getCon()->prefix() && $req->post( 'exec' ) == $nonceActionID ) {

			if ( !$opts->canIpRequestAutoUnblock( $ip ) ) {
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
			if ( wp_verify_nonce( $req->post( 'exec_nonce' ), $nonceActionID ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}

			$canUnblock = true;
		}

		return $canUnblock;
	}

	protected function getNonceAction() :string {
		return 'uau-'.Services::Request()->ip();
	}
}