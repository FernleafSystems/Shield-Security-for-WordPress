<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\SessionController
	 */
	private $sessionCon;

	public function getSessionCon() :Lib\SessionController {
		if ( !isset( $this->sessionCon ) ) {
			$this->sessionCon = ( new  Lib\SessionController() )
				->setMod( $this );
		}
		return $this->sessionCon;
	}

	public function getDbHandler_Sessions() :Databases\Session\Handler {
		return $this->getDbH( 'sessions' );
	}

	public function isAutoAddSessions() :bool {
		$opts = $this->getOptions();
		$req = Services::Request();
		$nStartedAt = $opts->getOpt( 'autoadd_sessions_started_at', 0 );
		if ( $nStartedAt < 1 ) {
			$nStartedAt = $req->ts();
			$opts->setOpt( 'autoadd_sessions_started_at', $nStartedAt );
		}
		return ( $req->ts() - $nStartedAt ) < 20;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return ( $this->getDbHandler_Sessions() instanceof Databases\Session\Handler )
			   && $this->getDbHandler_Sessions()->isReady()
			   && parent::isReadyToExecute();
	}
}