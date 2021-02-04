<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

class Traffic extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$params = $this->getParams();
		/** @var Databases\Traffic\Select $select */
		$select = $this->getWorkingSelector();

		$oIp = Services::IP();
		// If an IP is specified, it takes priority
		if ( $oIp->isValidIp( $params[ 'fIp' ] ) ) {
			$select->filterByIp( inet_pton( $params[ 'fIp' ] ) );
		}
		elseif ( $params[ 'fExcludeYou' ] == 'Y' ) {
			$select->filterByNotIp( inet_pton( $oIp->getRequestIp() ) );
		}

		// if username is provided, this takes priority over "logged-in" (even if it's invalid)
		if ( !empty( $params[ 'fUsername' ] ) ) {
			$oUser = Services::WpUsers()->getUserByUsername( $params[ 'fUsername' ] );
			if ( !empty( $oUser ) ) {
				$select->filterByUserId( $oUser->ID );
			}
		}
		elseif ( $params[ 'fLoggedIn' ] >= 0 ) {
			$select->filterByIsLoggedIn( $params[ 'fLoggedIn' ] );
		}

		if ( $params[ 'fOffense' ] >= 0 ) {
			$select->filterByIsTransgression( $params[ 'fOffense' ] );
		}

		$select->filterByPathContains( $params[ 'fPath' ] );
		$select->filterByResponseCode( $params[ 'fResponse' ] );

		return $this;
	}

	protected function buildEmpty() :string {
		return sprintf( '<div class="alert alert-success m-0">%s</div>',
			__( "No requests have been logged.", 'wp-simple-firewall' ) );
	}

	protected function getCustomParams() :array {
		return [
			'fIp'         => '',
			'fUsername'   => '',
			'fLoggedIn'   => -1,
			'fPath'       => '',
			'fOffense'    => -1,
			'fResponse'   => '',
			'fExcludeYou' => '',
		];
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$entries = [];

		$oWpUsers = Services::WpUsers();
		$oGeoIpLookup = ( new Lookup() )->setDbHandler( $this->getCon()
															 ->getModule_Plugin()
															 ->getDbHandler_GeoIp() );
		$srvIP = Services::IP();
		$you = $srvIP->getRequestIp();

		$users = [ 0 => __( 'No', 'wp-simple-firewall' ) ];
		$ipInfos = [];
		foreach ( $this->getEntriesRaw() as $key => $record ) {
			/** @var Databases\Traffic\EntryVO $record */
			$ip = $record->ip;

			list( $preQuery, $query ) = explode( '?', $record->path.'?', 2 );
			$query = trim( $query, '?' );
			$sPath = strtoupper( $record->verb ).': <code>'.$preQuery
					 .( empty( $query ) ? '' : '?<br/>'.$query ).'</code>';

			$sCodeType = 'success';
			if ( $record->code >= 400 ) {
				$sCodeType = 'danger';
			}
			elseif ( $record->code >= 300 ) {
				$sCodeType = 'warning';
			}

			$e = $record->getRawDataAsArray();
			$e[ 'path' ] = $sPath;
			$e[ 'code' ] = sprintf( '<span class="badge badge-%s">%s</span>', $sCodeType, $record->code );
			$e[ 'trans' ] = sprintf(
				'<span class="badge badge-%s">%s</span>',
				$record->trans ? 'danger' : 'info',
				$record->trans ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' )
			);
			$e[ 'ip' ] = $ip;
			$e[ 'created_at' ] = $this->formatTimestampField( $record->created_at );

			try {
				$e[ 'is_you' ] = $srvIP->checkIp( $you, $record->ip );
			}
			catch ( \Exception $e ) {
				$e[ 'is_you' ] = false;
			}
			$ipLink = sprintf( '%s%s',
				$this->getIpAnalysisLink( $record->ip ),
				$e[ 'is_you' ] ? ' <small>('.__( 'This Is You', 'wp-simple-firewall' ).')</small>' : ''
			);

			if ( $record->uid > 0 ) {
				if ( !isset( $users[ $record->uid ] ) ) {
					$user = $oWpUsers->getUserById( $record->uid );
					$users[ $record->uid ] = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) :
						sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
							$oWpUsers->getAdminUrl_ProfileEdit( $user ), $user->user_login );
				}
			}

			if ( !empty( $record->ip ) ) {
				if ( !isset( $ipInfos[ $record->ip ] ) ) {
					$ipInfos[ $record->ip ] = $this->getIpInfo( $record->ip );
				}
			}

			$geoIP = $oGeoIpLookup
				->setIP( $ip )
				->lookupIp();
			$countryISO = $geoIP->getCountryCode();
			if ( empty( $countryISO ) ) {
				$country = __( 'Unknown', 'wp-simple-firewall' );
			}
			else {
				$country = sprintf(
					'<img class="icon-flag" src="%s" alt="%s"/> %s',
					sprintf( 'https://www.countryflags.io/%s/flat/16.png', strtolower( $countryISO ) ),
					$countryISO,
					$geoIP->getCountryName()
				);
			}

			$e[ 'visitor' ] = sprintf( '<div>%s</div>', implode( '</div><div>', [
				sprintf( '%s: %s', __( 'IP', 'wp-simple-firewall' ), $ipLink ),
				sprintf( '%s: %s', __( 'IP Status', 'wp-simple-firewall' ), $ipInfos[ $record->ip ] ?? 'n/a' ),
				sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $users[ $record->uid ] ),
				sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $country ),
				esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $record->ua ) ) ),
			] ) );

			$e[ 'request_info' ] = sprintf( '<div>%s</div>', implode( '</div><div>', [
				sprintf( '%s: %s', __( 'Response', 'wp-simple-firewall' ), $e[ 'code' ] ),
				sprintf( '%s: %s', __( 'Offense', 'wp-simple-firewall' ), $e[ 'trans' ] ),
			] ) );

			$entries[ $key ] = $e;
		}
		return $entries;
	}

	private function getIpInfo( string $ip ) :string {
		$record = ( new LookupIpOnList() )
			->setDbHandler( $this->getCon()->getModule_IPs()->getDbHandler_IPs() )
			->setIP( $ip )
			->lookup();

		$badgeTemplate = '<span class="badge badge-%s">%s</span>';
		if ( $record->blocked_at > 0 || $record->list === ModCon::LIST_MANUAL_BLACK ) {
			$status = sprintf( $badgeTemplate, 'danger', __( 'Blocked', 'wp-simple-firewall' ) );
		}
		elseif ( $record->list === ModCon::LIST_AUTO_BLACK ) {
			$status = sprintf( $badgeTemplate,
				'warning',
				sprintf( _n( '%s offense', '%s offenses', $record->transgressions, 'wp-simple-firewall' ), $record->transgressions )
			);
		}
		elseif ( $record->list === ModCon::LIST_MANUAL_WHITE ) {
			$status = sprintf( $badgeTemplate,
				'success',
				__( 'Bypass', 'wp-simple-firewall' )
			);
		}
		else {
			$status = __( 'No Record', 'wp-simple-firewall' );
		}
		return $status;
	}

	/**
	 * @return Tables\Render\WpListTable\Traffic
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\Traffic();
	}
}