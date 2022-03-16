<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class AddIP extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = $this->getRequestVO();

		$adder = ( new Lib\Ops\AddIp() )
			->setMod( $mod )
			->setIP( $req->ip );
		if ( $req->list === 'block' ) {
			$IP = $adder->toManualBlacklist( $req->label );
		}
		elseif ( $req->list === 'bypass' ) {
			$IP = $adder->toManualWhitelist( $req->label );
		}

		if ( empty( $IP ) ) {
			throw new \Exception( 'There was an error adding IP address to list.' );
		}

		return [
			'ip' => $this->getIpData( $req->ip, $req->list )
		];
	}
}