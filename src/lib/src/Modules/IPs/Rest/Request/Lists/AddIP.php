<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class AddIP extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = $this->getRequestVO();

		$adder = ( new Lib\Ops\AddRule() )
			->setMod( $mod )
			->setIP( $req->ip );

		try {
			if ( in_array( $req->list, [ 'block', 'black' ] ) ) {
				$IP = $adder->toManualBlacklist( $req->label );
			}
			elseif ( in_array( $req->list, [ 'bypass', 'white' ] ) ) {
				$IP = $adder->toManualWhitelist( $req->label );
			}
		}
		catch ( \Exception $e ) {
			throw new ApiException( 'There was an error adding IP address to list.' );
		}

		return [
			'ip' => $this->getIpData( $req->ip, $req->list )
		];
	}
}