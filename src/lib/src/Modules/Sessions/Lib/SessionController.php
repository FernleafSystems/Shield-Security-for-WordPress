<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;
use FernleafSystems\Wordpress\Services\Services;

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
		if ( empty( $this->current ) && did_action( 'init' ) ) {
			if ( $this->hasSessionID() ) {
				$this->current = $this->queryGetSession( $this->getSessionID() );
			}
			if ( empty( $this->current ) && $con->hasSessionId() ) {
				$this->current = $this->queryGetSession( $con->getSessionId() );
			}
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

	public function createSession( \WP_User $user, string $sessionID = '' ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$success = false;
		if ( empty( $sessionID ) ) {
			$sessionID = $this->getSessionID();
		}

		if ( !empty( $sessionID ) && !empty( $user->user_login ) ) {

			if ( !preg_match( '#^[a-z0-9]{32}$#i', $sessionID ) ) {
				$sessionID = md5( $sessionID );
			}

			/** @var Session\Insert $insert */
			$insert = $mod->getDbHandler_Sessions()->getQueryInserter();
			$success = $insert->create( $sessionID, $user->user_login );

			$this->getCon()->fireEvent( 'session_start' );
		}
		return $success;
	}

	public function hasSessionID() :bool {
		return !empty( $this->getSessionID() );
	}

	public function getSessionID() :string {
		$cookie = Services::Request()->cookie( LOGGED_IN_COOKIE );
		return empty( $cookie ) ? '' : md5( $cookie );
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