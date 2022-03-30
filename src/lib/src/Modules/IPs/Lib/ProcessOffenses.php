<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class ProcessOffenses extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return !$this->getCon()->this_req->is_trusted_bot;
	}

	protected function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$mod->loadOffenseTracker()->setIfCommit( true );

		$con = $this->getCon();
		add_filter( 'shield/firewall_die_message', [ $this, 'augmentFirewallDieMessage' ] );
		add_action( $con->prefix( 'pre_plugin_shutdown' ), function () {
			$this->processOffense();
		} );
		add_action( 'shield_security_offense', [ $this, 'processCustomShieldOffense' ], 10, 3 );
	}

	private function processOffense() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$tracker = $mod->loadOffenseTracker();
		if ( !$this->getCon()->plugin_deleting
			 && $tracker->hasVisitorOffended() && $tracker->isCommit() ) {
			( new IPs\Components\ProcessOffense() )
				->setMod( $mod )
				->setIp( Services::IP()->getRequestIp() )
				->run();
		}
	}

	/**
	 * @param array $msg
	 * @return array
	 */
	public function augmentFirewallDieMessage( $msg ) {
		if ( !is_array( $msg ) ) {
			$msg = [];
		}

		$msg[] = sprintf( '<p>%s</p>', sprintf(
			$this->getMod()->getTextOpt( 'text_remainingtrans' ),
			max( 0, ( new IPs\Components\QueryRemainingOffenses() )
				->setMod( $this->getMod() )
				->setIP( Services::IP()->getRequestIp() )
				->run() )
		) );

		return $msg;
	}

	/**
	 * Allows 3rd parties to trigger Shield offenses
	 * @param string $message
	 * @param int    $offenseCount
	 * @param bool   $includedLoggedIn
	 */
	public function processCustomShieldOffense( $message, $offenseCount = 1, $includedLoggedIn = true ) {
		if ( $this->getCon()->isPremiumActive() ) {
			if ( empty( $message ) ) {
				$message = __( 'No custom message provided.', 'wp-simple-firewall' );
			}

			if ( $includedLoggedIn || !did_action( 'init' ) || !Services::WpUsers()->isUserLoggedIn() ) {
				$this->getCon()
					 ->fireEvent(
						 'custom_offense',
						 [
							 'audit_params'  => [ 'message' => $message ],
							 'offense_count' => (int)$offenseCount
						 ]
					 );
			}
		}
	}
}