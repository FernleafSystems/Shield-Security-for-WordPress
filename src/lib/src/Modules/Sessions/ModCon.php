<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\SessionController
	 */
	private $sessionCon;

	public function getSessionCon() :Lib\SessionController {
		if ( !isset( $this->sessionCon ) ) {
			$this->sessionCon = ( new  Lib\SessionController() )->setMod( $this );
		}
		return $this->sessionCon;
	}
}