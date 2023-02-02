<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	/**
	 * @var Lib\SessionController
	 */
	private $sessionCon;

	public function getSessionCon() :Lib\SessionController {
		return $this->sessionCon ?? $this->sessionCon = ( new  Lib\SessionController() )->setMod( $this );
	}
}