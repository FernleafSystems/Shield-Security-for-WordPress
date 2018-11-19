<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class LiveTraffic
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class LiveTraffic extends Base {

	/**
	 * @var string
	 */
	private $sGeoIpDbSource;

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyQueryFilters() {
		$aParams = $this->getParams();
		/** @var \ICWP_WPSF_Query_TrafficEntry_Select $oSelector */
		$oSelector = $this->getQuerySelector();

		$oIp = Services::IP();
		// If an IP is specified, it takes priority
		if ( $oIp->isValidIp( $aParams[ 'fIp' ] ) ) {
			$oSelector->filterByIp( inet_pton( $aParams[ 'fIp' ] ) );
		}
		else if ( $aParams[ 'fExludeYou' ] == 'Y' ) {
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

		if ( $aParams[ 'fTransgression' ] >= 0 ) {
			$oSelector->filterByIsTransgression( $aParams[ 'fTransgression' ] );
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
		return array(
			'fIp'            => '',
			'fUsername'      => '',
			'fLoggedIn'      => -1,
			'fPath'          => '',
			'fTransgression' => -1,
			'fResponse'      => '',
			'fExludeYou'     => '',
		);
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		$oWpUsers = Services::WpUsers();
		$oGeo = Services::GeoIp()->setDbSource( $this->getGeoIpDbSource() );
		$oIp = Services::IP();
		$sYou = $oIp->getRequestIp();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var \ICWP_WPSF_TrafficEntryVO $oEntry */
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

			$aEntry = $oEntry->getRawData();
			$aEntry[ 'path' ] = $sPath;
			$aEntry[ 'code' ] = sprintf( '<span class="badge badge-%s">%s</span>', $sCodeType, $oEntry->code );
			$aEntry[ 'trans' ] = sprintf(
				'<span class="badge badge-%s">%s</span>',
				$oEntry->trans ? 'danger' : 'info',
				$oEntry->trans ? _wpsf__( 'Yes' ) : _wpsf__( 'No' )
			);
			$aEntry[ 'ip' ] = $sIp;
			$aEntry[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntry[ 'is_you' ] = $sIp == $sYou;

			if ( $oEntry->uid > 0 ) {
				if ( !isset( $aUsers[ $oEntry->uid ] ) ) {
					$oUser = $oWpUsers->getUserById( $oEntry->uid );
					$aUsers[ $oEntry->uid ] = empty( $oUser ) ? _wpsf__( 'unknown' ) :
						sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
							$oWpUsers->getAdminUrl_ProfileEdit( $oUser ), $oUser->user_login );
				}
			}

			$sCountry = $oGeo->countryName( $sIp );
			if ( empty( $sCountry ) ) {
				$sCountry = _wpsf__( 'Unknown' );
			}
			else {
				$sFlag = sprintf( 'https://www.countryflags.io/%s/flat/16.png', strtolower( $oGeo->countryIso( $sIp ) ) );
				$sCountry = sprintf( '<img class="icon-flag" src="%s"/> %s', $sFlag, $sCountry );
			}

			$sIpLink = sprintf( '<a href="%s" target="_blank" title="IP Whois">%s</a>%s',
				$oIp->getIpWhoisLookup( $sIp ), $sIp,
				$aEntry[ 'is_you' ] ? ' <span style="font-size: smaller;">('._wpsf__( 'You' ).')</span>' : ''
			);

			$aDetails = array(
				sprintf( '%s: %s', _wpsf__( 'IP' ), $sIpLink ),
				sprintf( '%s: %s', _wpsf__( 'Logged-In' ), $aUsers[ $oEntry->uid ] ),
				sprintf( '%s: %s', _wpsf__( 'Location' ), $sCountry ),
				esc_html( esc_js( sprintf( '%s - %s', _wpsf__( 'User Agent' ), $oEntry->ua ) ) )
			);
			$aEntry[ 'visitor' ] = '<div>'.implode( '</div><div>', $aDetails ).'</div>';

			$aInfo = array(
				sprintf( '%s: %s', _wpsf__( 'Response' ), $aEntry[ 'code' ] ),
				sprintf( '%s: %s', _wpsf__( 'Transgression' ), $aEntry[ 'trans' ] ),
			);
			$aEntry[ 'request_info' ] = '<div>'.implode( '</div><div>', $aInfo ).'</div>';
			$aEntries[ $nKey ] = $aEntry;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\LiveTraffic
	 */
	protected function getTableRenderer() {
		return new Tables\Render\LiveTraffic();
	}

	/**
	 * @return string
	 */
	public function getGeoIpDbSource() {
		return $this->sGeoIpDbSource;
	}

	/**
	 * @param string $sGeoIpDbSource
	 * @return $this
	 */
	public function setGeoIpDbSource( $sGeoIpDbSource ) {
		$this->sGeoIpDbSource = $sGeoIpDbSource;
		return $this;
	}
}