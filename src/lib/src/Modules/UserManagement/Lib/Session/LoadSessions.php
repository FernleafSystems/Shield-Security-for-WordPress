<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

class LoadSessions {

	use ModConsumer;

	/**
	 * @var array[]
	 */
	private $sessions;

	private $maxSessions;

	private $maxUsers;

	private $userID;

	public function __construct( ?int $userID = null, $maxUsers = 1000, $maxSessions = 10000 ) {
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
		if ( !isset( $this->sessions ) ) {
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

	private function queryUserMetaForIDs( int $page ) :array {
		// Select the most recently active based on updated Shield User Meta
		/** @var Select $metaSelect */
		$metaSelect = self::con()
						  ->getModule_Data()
						  ->getDbH_UserMeta()
						  ->getQuerySelector();
		if ( !empty( $this->userID ) ) {
			$metaSelect->filterByUser( $this->userID );
		}
		$results = $metaSelect->setResultsAsVo( false )
							  ->setSelectResultsFormat( ARRAY_A )
							  ->setColumnsToSelect( [ 'user_id' ] )
							  ->setOrderBy( 'updated_at' )
							  ->setPage( $page )
							  ->setLimit( 200 )
							  ->queryWithResult();
		return \array_map(
			function ( $res ) {
				return (int)$res[ 'user_id' ];
			},
			\is_array( $results ) ? $results : []
		);
	}
}