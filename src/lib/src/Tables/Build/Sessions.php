<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

class Sessions extends BaseBuild {

	/**
	 * @var array
	 */
	private $sessions;

	public function countTotal() :int {
		return (int)\array_sum( \array_map( function ( $sessions ) {
			return \count( $sessions );
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
				$metaSelect = $this->con()
								   ->getModule_Data()
								   ->getDbH_UserMeta()
								   ->getQuerySelector();
				$results = $metaSelect->setResultsAsVo( false )
									  ->setSelectResultsFormat( ARRAY_A )
									  ->setColumnsToSelect( [ 'user_id' ] )
									  ->setOrderBy( 'updated_at' )
									  ->setLimit( 20 )
									  ->queryWithResult();
				$UIDs = \array_map(
					function ( $res ) {
						return (int)$res[ 'user_id' ];
					},
					\is_array( $results ) ? $results : []
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

		$allSessions = \array_filter( $allSessions );

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
		$entries = [];

		$srvIP = Services::IP();
		$WPU = Services::WpUsers();
		$you = $this->con()->this_req->ip;
		foreach ( $this->getEntriesRaw() as $key => $e ) {

			try {
				$isYou = $srvIP::IpIn( $you, [ $e[ 'ip' ] ] );
			}
			catch ( \Exception $ex ) {
				$isYou = false;
			}

			$entries[ $key ] = \array_merge( $e, [
				'is_secadmin'      => $e[ 'secadmin_at' ] ? __( 'Yes' ) : __( 'No' ),
				'last_activity_at' => $this->formatTimestampField( $e[ 'last_activity_at' ] ),
				'logged_in_at'     => $this->formatTimestampField( $e[ 'login' ] ),
				'ip'               => sprintf( '%s <small>%s</small>',
					$this->getIpAnalysisLink( $e[ 'ip' ] ),
					$isYou ? __( 'You', 'wp-simple-firewall' ) : ''
				),
				'is_you'           => $isYou,
				'wp_username'      => sprintf( '<a href="%s">%s</a>',
					$WPU->getAdminUrl_ProfileEdit( $e[ 'user' ] ),
					$e[ 'user' ]->user_login
				),
			] );
		}
		return $entries;
	}

	/**
	 * @return Tables\Render\WpListTable\Sessions
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\Sessions();
	}

	protected function getIpAnalysisLink( string $ip ) :string {
		$srvIP = Services::IP();
		$href = $srvIP->isValidIpRange( $ip ) ? $srvIP->getIpWhoisLookup( $ip ) : $this->con()->plugin_urls->ipAnalysis( $ip );
		return sprintf(
			'<a href="%s" %s title="%s" class="ip-whois %s" data-ip="%s">%s</a>',
			$href,
			$srvIP->isValidIpRange( $ip ) ? 'target="_blank"' : '',
			__( 'IP Analysis' ),
			$srvIP->isValidIpRange( $ip ) ? '' : 'render_ip_analysis',
			$ip,
			$ip
		);
	}
}