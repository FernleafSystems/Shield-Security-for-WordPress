<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Services\Services;

class LoadSessions extends SessionsBase {

	/**
	 * @var array[]
	 */
	private ?array $sessions = null;

	private int $maxSessions;

	private int $maxUsers;

	private ?int $userID;

	public function __construct( ?int $userID = null, int $maxUsers = 1000, int $maxSessions = 10000 ) {
		$this->userID = $userID;
		$this->maxUsers = $maxUsers;
		$this->maxSessions = $maxSessions;
	}

	public function count() :int {
		return \array_sum( \array_map( '\count', $this->all() ) );
	}

	public function allOrderedByLastActivityAt() :array {
		$all = $this->flat();
		\usort( $all, function ( array $a, array $b ) {
			$activityA = $a[ 'shield' ][ 'last_activity_at' ];
			$activityB = $b[ 'shield' ][ 'last_activity_at' ];
			return $activityA > $activityB ? -1 : ( $activityB > $activityA ? 1 : 0 );
		} );
		return $all;
	}

	public function flat() :array {
		$flat = [];
		foreach ( $this->all() as $sessions ) {
			$flat = \array_merge( $flat, $sessions );
		}
		return $flat;
	}

	public function all() :array {
		if ( $this->sessions === null ) {
			$this->sessions = [];
			$page = 1;
			do {
				/** If we're after a single User, bypass the user meta query, obviously. */
				if ( empty( $this->userID ) ) {
					$UIDs = $this->queryUserMetaForIDs( $page );
				}
				else {
					$UIDs = $page === 1 ? [ $this->userID ] : [];
				}

				foreach ( $UIDs as $UID ) {
					foreach ( \WP_Session_Tokens::get_instance( $UID )->get_all() as $session ) {
						if ( !isset( $this->sessions[ $UID ] ) ) {
							$this->sessions[ $UID ] = [];
						}
						if ( Services::Request()->ts() <= $session[ 'expiration' ] && !empty( $session[ 'shield' ] ) ) {
							$this->sessions[ $UID ][] = $session;
						}
					}
					$this->sessions = \array_filter( $this->sessions );
				}

				if ( empty( $UIDs )
					 || \count( $this->sessions ) > $this->maxUsers
					 || \array_sum( \array_map( '\count', $this->sessions ) ) > $this->maxSessions ) {
					break;
				}

				$page++;
			} while ( true );
		}
		return $this->sessions;
	}
}