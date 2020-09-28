<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Traffic
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class Traffic extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$aParams = $this->getParams();
		/** @var Databases\Traffic\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

		$oIp = Services::IP();
		// If an IP is specified, it takes priority
		if ( $oIp->isValidIp( $aParams[ 'fIp' ] ) ) {
			$oSelector->filterByIp( inet_pton( $aParams[ 'fIp' ] ) );
		}
		elseif ( $aParams[ 'fExcludeYou' ] == 'Y' ) {
			$oSelector->filterByNotIp( inet_pton( $oIp->getRequestIp() ) );
		}

		// if username is provided, this takes priority over "logged-in" (even if it's invalid)
		if ( !empty( $aParams[ 'fUsername' ] ) ) {
			$oUser = Services::WpUsers()->getUserByUsername( $aParams[ 'fUsername' ] );
			if ( !empty( $oUser ) ) {
				$oSelector->filterByUserId( $oUser->ID );
			}
		}
		elseif ( $aParams[ 'fLoggedIn' ] >= 0 ) {
			$oSelector->filterByIsLoggedIn( $aParams[ 'fLoggedIn' ] );
		}

		if ( $aParams[ 'fOffense' ] >= 0 ) {
			$oSelector->filterByIsTransgression( $aParams[ 'fOffense' ] );
		}

		$oSelector->filterByPathContains( $aParams[ 'fPath' ] );
		$oSelector->filterByResponseCode( $aParams[ 'fResponse' ] );

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
		$aEntries = [];

		$oWpUsers = Services::WpUsers();
		$oGeoIpLookup = ( new Lookup() )->setDbHandler( $this->getCon()
															 ->getModule_Plugin()
															 ->getDbHandler_GeoIp() );
		$srvIP = Services::IP();
		$you = $srvIP->getRequestIp();

		$aUsers = [ 0 => __( 'No', 'wp-simple-firewall' ) ];
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Databases\Traffic\EntryVO $oEntry */
			$ip = $oEntry->ip;

			list( $sPreQuery, $sQuery ) = explode( '?', $oEntry->path.'?', 2 );
			$sQuery = trim( $sQuery, '?' );
			$sPath = strtoupper( $oEntry->verb ).': <code>'.$sPreQuery
					 .( empty( $sQuery ) ? '' : '?<br/>'.$sQuery ).'</code>';

			$sCodeType = 'success';
			if ( $oEntry->code >= 400 ) {
				$sCodeType = 'danger';
			}
			elseif ( $oEntry->code >= 300 ) {
				$sCodeType = 'warning';
			}

			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'path' ] = $sPath;
			$aE[ 'code' ] = sprintf( '<span class="badge badge-%s">%s</span>', $sCodeType, $oEntry->code );
			$aE[ 'trans' ] = sprintf(
				'<span class="badge badge-%s">%s</span>',
				$oEntry->trans ? 'danger' : 'info',
				$oEntry->trans ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' )
			);
			$aE[ 'ip' ] = $ip;
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );

			try {
				$aE[ 'is_you' ] = $srvIP->checkIp( $you, $oEntry->ip );
			}
			catch ( \Exception $e ) {
				$aE[ 'is_you' ] = false;
			}
			$sIpLink = sprintf( '%s%s',
				$this->getIpAnalysisLink( $oEntry->ip ),
				$aE[ 'is_you' ] ? ' <small>'.__( 'You', 'wp-simple-firewall' ).')</small>' : ''
			);

			if ( $oEntry->uid > 0 ) {
				if ( !isset( $aUsers[ $oEntry->uid ] ) ) {
					$oUser = $oWpUsers->getUserById( $oEntry->uid );
					$aUsers[ $oEntry->uid ] = empty( $oUser ) ? __( 'Unknown', 'wp-simple-firewall' ) :
						sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
							$oWpUsers->getAdminUrl_ProfileEdit( $oUser ), $oUser->user_login );
				}
			}

			$oGeoIp = $oGeoIpLookup
				->setIP( $ip )
				->lookupIp();
			$sCountryIso = $oGeoIp->getCountryCode();
			if ( empty( $sCountryIso ) ) {
				$sCountry = __( 'Unknown', 'wp-simple-firewall' );
			}
			else {
				$sFlag = sprintf( 'https://www.countryflags.io/%s/flat/16.png', strtolower( $sCountryIso ) );
				$sCountry = sprintf( '<img class="icon-flag" src="%s" alt="%s"/> %s', $sFlag, $sCountryIso, $oGeoIp->getCountryName() );
			}

			$aDetails = [
				sprintf( '%s: %s', __( 'IP', 'wp-simple-firewall' ), $sIpLink ),
				sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $aUsers[ $oEntry->uid ] ),
				sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $sCountry ),
				esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $oEntry->ua ) ) )
			];
			$aE[ 'visitor' ] = '<div>'.implode( '</div><div>', $aDetails ).'</div>';

			$aInfo = [
				sprintf( '%s: %s', __( 'Response', 'wp-simple-firewall' ), $aE[ 'code' ] ),
				sprintf( '%s: %s', __( 'Offense', 'wp-simple-firewall' ), $aE[ 'trans' ] ),
			];
			$aE[ 'request_info' ] = '<div>'.implode( '</div><div>', $aInfo ).'</div>';
			$aEntries[ $nKey ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\WpListTable\Traffic
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\Traffic();
	}
}