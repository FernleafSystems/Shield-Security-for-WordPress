<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

class Sessions extends BaseBuild {

	/**
	 * @var string[]
	 */
	private $aSecAdminUsers;

	/**
	 * @var array
	 */
	private $sessions;

	/**
	 * @return int
	 */
	public function countTotal() {
		return array_sum( array_map( function ( $sessions ) {
			return count( $sessions );
		}, $this->loadSessions() ) );
	}

	private function loadSessions() :array {
		if ( !isset( $this->sessions ) ) {

			$params = $this->getParams();
			if ( !empty( $params[ 'fUsername' ] ) ) {
				$user = Services::WpUsers()->getUserByUsername( $params[ 'fUsername' ] );
				if ( !empty( $user ) ) {
					$UIDs = [ $user->ID ];
				}
			}

			if ( empty( $UIDs ) ) {
				// Select the most recently active based on updated Shield User Meta
				/** @var Select $metaSelect */
				$metaSelect = $this->getCon()
								   ->getModule_Data()
								   ->getDbH_UserMeta()
								   ->getQuerySelector();
				$results = $metaSelect->setResultsAsVo( false )
									  ->setSelectResultsFormat( ARRAY_A )
									  ->setColumnsToSelect( [ 'user_id' ] )
									  ->setOrderBy( 'updated_at', 'DESC' )
									  ->setLimit( 20 )
									  ->queryWithResult();
				$UIDs = array_map(
					function ( $res ) {
						return (int)$res[ 'user_id' ];
					},
					is_array( $results ) ? $results : []
				);
			}

			$this->sessions = [];
			foreach ( $UIDs as $UID ) {
				$manager = \WP_Session_Tokens::get_instance( $UID );
				$this->sessions[ $UID ] = $manager->get_all();
			}
		}
		return $this->sessions;
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesRaw() :array {
		$allSessions = [];
		foreach ( $this->loadSessions() as $uid => $sessions ) {
			$user = Services::WpUsers()->getUserById( $uid );
			foreach ( $sessions as $session ) {
				$session[ 'last_activity_at' ] = $session[ 'shield' ][ 'last_activity_at' ] ?? $session[ 'login' ];
				$session[ 'secadmin_at' ] = $session[ 'shield' ][ 'secadmin_at' ] ?? 0;
				$session[ 'user' ] = $user;
				$session[ 'user_id' ] = $user->ID;
				$allSessions[] = $session;
			}
		}

		$allSessions = array_filter( $allSessions );

		usort( $allSessions, function ( $a, $b ) {
			$a = $a[ 'last_activity_at' ] ?? $a[ 'login' ];
			$b = $b[ 'last_activity_at' ] ?? $b[ 'login' ];
			if ( $a == $b ) {
				return 0;
			}
			return ( $a < $b ) ? 1 : -1;
		} );

		return $allSessions;
	}

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		/** @var Session\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

		$params = $this->getParams();

		// If an IP is specified, it takes priority
		if ( Services::IP()->isValidIp( $params[ 'fIp' ] ) ) {
			$oSelector->filterByIp( $params[ 'fIp' ] );
		}

		$oSelector->setOrderBy( 'last_activity_at', 'DESC', true );

		return $this;
	}

	protected function getCustomParams() :array {
		return [
			'fIp'       => '',
			'fUsername' => '',
		];
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$aEntries = [];

		$srvIP = Services::IP();
		$you = $srvIP->getRequestIp();
		foreach ( $this->getEntriesRaw() as $nKey => $e ) {
			$e[ 'is_secadmin' ] = $e[ 'secadmin_at' ] ? __( 'Yes' ) : __( 'No' );
			$e[ 'last_activity_at' ] = $this->formatTimestampField( $e[ 'last_activity_at' ] );
			$e[ 'logged_in_at' ] = $this->formatTimestampField( $e[ 'login' ] );

			try {
				$e[ 'is_you' ] = $srvIP->checkIp( $you, $e[ 'ip' ] );
			}
			catch ( \Exception $ex ) {
				$e[ 'is_you' ] = false;
			}
			$e[ 'ip' ] = sprintf( '%s%s',
				$this->getIpAnalysisLink( $e[ 'ip' ] ),
				$e[ 'is_you' ] ? ' <small>('.__( 'You', 'wp-simple-firewall' ).')</small>' : ''
			);

			$WPU = Services::WpUsers();
			$e[ 'wp_username' ] = sprintf(
				'<a href="%s">%s</a>',
				$WPU->getAdminUrl_ProfileEdit( $e[ 'user' ] ),
				$e[ 'user' ]->user_login
			);
			$aEntries[ $nKey ] = $e;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\WpListTable\Sessions
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\Sessions();
	}

	/**
	 * @param Session\EntryVO $oEntry
	 * @return bool
	 */
	private function isSecAdminSession( $oEntry ) {
		return ( $oEntry->getSecAdminAt() > 0 ) ||
			   ( is_array( $this->aSecAdminUsers ) && in_array( $oEntry->wp_username, $this->aSecAdminUsers ) );
	}

	/**
	 * @param array $aSecAdminUsernames
	 * @return $this
	 */
	public function setSecAdminUsers( $aSecAdminUsernames ) {
		$this->aSecAdminUsers = $aSecAdminUsernames;
		return $this;
	}
}