<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class LiveTraffic
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
		else if ( $aParams[ 'fExcludeYou' ] == 'Y' ) {
			$oSelector->filterByNotIp( inet_pton( $oIp->getRequestIp() ) );
		}

		// if username is provided, this takes priority over "logged-in" (even if it's invalid)
		if ( !empty( $aParams[ 'fUsername' ] ) ) {
			$oUser = Services::WpUsers()->getUserByUsername( $aParams[ 'fUsername' ] );
			if ( !empty( $oUser ) ) {
				$oSelector->filterByUserId( $oUser->ID );
			}
		}
		else if ( $aParams[ 'fLoggedIn' ] >= 0 ) {
			$oSelector->filterByIsLoggedIn( $aParams[ 'fLoggedIn' ] );
		}

		if ( $aParams[ 'fOffense' ] >= 0 ) {
			$oSelector->filterByIsTransgression( $aParams[ 'fOffense' ] );
		}

		$oSelector->filterByPathContains( $aParams[ 'fPath' ] );
		$oSelector->filterByResponseCode( $aParams[ 'fResponse' ] );

		return $this;
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
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
	protected function getEntriesFormatted() {
		$aEntries = [];

		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();

		$oWpUsers = Services::WpUsers();
		$oGeoIpLookup = ( new Lookup() )->setDbHandler( $oMod->getDbHandler_GeoIp() );
		$oIpSrv = Services::IP();
		$sYou = $oIpSrv->getRequestIp();

		$aUsers = [ 0 => __( 'No', 'wp-simple-firewall' ) ];
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Databases\Traffic\EntryVO $oEntry */
			$sIp = $oEntry->ip;

			list( $sPreQuery, $sQuery ) = explode( '?', $oEntry->path.'?', 2 );
			$sQuery = trim( $sQuery, '?' );
			$sPath = strtoupper( $oEntry->verb ).': <code>'.$sPreQuery
					 .( empty( $sQuery ) ? '' : '?<br/>'.$sQuery ).'</code>';

			$sCodeType = 'success';
			if ( $oEntry->code >= 400 ) {
				$sCodeType = 'danger';
			}
			else if ( $oEntry->code >= 300 ) {
				$sCodeType = 'warning';
			}

			$aEntry = $oEntry->getRawDataAsArray();
			$aEntry[ 'path' ] = $sPath;
			$aEntry[ 'code' ] = sprintf( '<span class="badge badge-%s">%s</span>', $sCodeType, $oEntry->code );
			$aEntry[ 'trans' ] = sprintf(
				'<span class="badge badge-%s">%s</span>',
				$oEntry->trans ? 'danger' : 'info',
				$oEntry->trans ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' )
			);
			$aEntry[ 'ip' ] = $sIp;
			$aEntry[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntry[ 'is_you' ] = $sIp == $sYou;

			if ( $oEntry->uid > 0 ) {
				if ( !isset( $aUsers[ $oEntry->uid ] ) ) {
					$oUser = $oWpUsers->getUserById( $oEntry->uid );
					$aUsers[ $oEntry->uid ] = empty( $oUser ) ? __( 'Unknown', 'wp-simple-firewall' ) :
						sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
							$oWpUsers->getAdminUrl_ProfileEdit( $oUser ), $oUser->user_login );
				}
			}

			$oGeoIp = $oGeoIpLookup->lookupIp( $sIp );
			$sCountryIso = $oGeoIp->getCountryCode();
			if ( empty( $sCountryIso ) ) {
				$sCountry = __( 'Unknown', 'wp-simple-firewall' );
			}
			else {
				$sFlag = sprintf( 'https://www.countryflags.io/%s/flat/16.png', strtolower( $sCountryIso ) );
				$sCountry = sprintf( '<img class="icon-flag" src="%s" alt="%s"/> %s', $sFlag, $sCountryIso, $oGeoIp->getCountryName() );
			}

			$sIpLink = sprintf( '<a href="%s" target="_blank" title="IP Whois">%s</a>%s',
				$oIpSrv->getIpWhoisLookup( $sIp ), $sIp,
				$aEntry[ 'is_you' ] ? ' <span style="font-size: smaller;">('.__( 'You', 'wp-simple-firewall' ).')</span>' : ''
			);

			$aDetails = [
				sprintf( '%s: %s', __( 'IP', 'wp-simple-firewall' ), $sIpLink ),
				sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $aUsers[ $oEntry->uid ] ),
				sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $sCountry ),
				esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $oEntry->ua ) ) )
			];
			$aEntry[ 'visitor' ] = '<div>'.implode( '</div><div>', $aDetails ).'</div>';

			$aInfo = [
				sprintf( '%s: %s', __( 'Response', 'wp-simple-firewall' ), $aEntry[ 'code' ] ),
				sprintf( '%s: %s', __( 'Offense', 'wp-simple-firewall' ), $aEntry[ 'trans' ] ),
			];
			$aEntry[ 'request_info' ] = '<div>'.implode( '</div><div>', $aInfo ).'</div>';
			$aEntries[ $nKey ] = $aEntry;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\Traffic
	 */
	protected function getTableRenderer() {
		return new Tables\Render\Traffic();
	}
}