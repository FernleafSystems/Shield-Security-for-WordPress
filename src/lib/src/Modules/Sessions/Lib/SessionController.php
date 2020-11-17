<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;

class SessionController {

	use ModConsumer;

	/**
	 * @var Session\EntryVO
	 */
	private $current;

	/**
	 * @return Session\EntryVO|null
	 */
	public function getCurrent() {
		$con = $this->getCon();
		if ( empty( $this->current ) && did_action( 'init' ) && $con->hasSessionId() ) {
			$this->current = $this->queryGetSession( $con->getSessionId() );
		}
		return $this->current;
	}

	public function hasSession() :bool {
		$s = $this->getCurrent();
		return $s instanceof Session\EntryVO && $s->id > 0;
	}

	public function terminateCurrentSession() :bool {
		$current = $this->getCurrent();

		$success = $current instanceof Session\EntryVO
				   && ( new Ops\Terminate() )
					   ->setMod( $this->getMod() )
					   ->byRecordId( $current->id );

		$this->current = null;
		$this->getCon()->clearSession();

		return $success;
	}

	public function queryCreateSession( string $sessionID, \WP_User $user ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$success = false;
		if ( !empty( $sessionID ) && !empty( $user->user_login ) ) {
			$this->getCon()->fireEvent( 'session_start' );
			/** @var Session\Insert $insert */
			$insert = $mod->getDbHandler_Sessions()->getQueryInserter();
			$success = $insert->create( $sessionID, $user->user_login );
		}
		return $success;
	}

	/**
	 * @param string $username
	 * @param string $sessionID
	 * @return Session\EntryVO|null
	 */
	private function queryGetSession( string $sessionID, $username = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Session\Select $sel */
		$sel = $mod->getDbHandler_Sessions()->getQuerySelector();
		return $sel->retrieveUserSession( $sessionID, $username );
	}
}