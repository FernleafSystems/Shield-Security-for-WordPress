<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class VisitorIpDetection
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities
 */
class VisitorIpDetection {

	const DEFAULT_SOURCE = 'REMOTE_ADDR';

	/**
	 * @var string[]
	 */
	private $aPotentialHostIps;

	/**
	 * @var string
	 */
	private $sLastSuccessfulSource;

	/**
	 * @var string
	 */
	private $sPreferredSource;

	/**
	 * @return string
	 */
	public function detect() {
		return $this->runNormalDetection();
	}

	/**
	 * Progressively removes Host IPs from the list so that these don't interfere with detection.
	 * @return string
	 */
	public function alternativeDetect() {
		do {
			$sIp = $this->runNormalDetection();
			if ( !empty( $sIp ) ) {
				break;
			}

			// Progressively remove a Host IP until there's none left.
			$aHostIps = $this->getPotentialHostIps();
			if ( empty( $aHostIps ) ) {
				break;
			}
			array_shift( $aHostIps );
			$this->setPotentialHostIps( $aHostIps );

		} while ( empty( $sIp ) );

		return $sIp;
	}

	/**
	 * @return string
	 */
	private function runNormalDetection() {
		$sSource = '';
		$aIps = $this->detectAndFilterFromSource( $this->getPreferredSource() );

		if ( empty( $aIps ) ) { // Couldn't detect IP from preferred source.

			foreach ( $this->getIpSourceOptions() as $sMaybeSource ) {
				$aIps = $this->detectAndFilterFromSource( $sMaybeSource );
				if ( !empty( $aIps ) ) {
					$sSource = $sMaybeSource;
					break;
				}
			}
		}
		else {
			$sSource = $this->getPreferredSource();
		}

		$this->sLastSuccessfulSource = $sSource;
		return empty( $aIps ) ? '' : array_shift( $aIps );
	}

	/**
	 * @param string $sSource
	 * @return string[]
	 */
	protected function detectAndFilterFromSource( $sSource ) {
		return $this->filterIpsByViable( $this->getIpsFromSource( $sSource ) );
	}

	/**
	 * @param string[] $aIps
	 * @return string[]
	 */
	protected function filterIpsByViable( $aIps ) {
		return array_values( array_filter(
			$aIps,
			function ( $sIp ) {
				$oIP = Services::IP();
				return ( $oIP->isValidIp_PublicRemote( $sIp )
						 && !$oIP->checkIp( $sIp, $this->getPotentialHostIps() )
						 && !$oIP->isCloudFlareIp( $sIp )
				);
			}
		) );
	}

	/**
	 * @param string $sSource
	 * @return string[]
	 */
	protected function getIpsFromSource( $sSource ) {
		$sRawSource = (string)Services::Request()->server( $sSource );
		$aRaw = empty( $sRawSource ) ? [] : explode( ',', $sRawSource );
		return array_filter(
			array_map( 'trim', $aRaw ),
			function ( $sIp ) {
				return filter_var( $sIp, FILTER_VALIDATE_IP ) !== false;
			}
		);
	}

	/**
	 * @return string[]
	 */
	public function getPotentialHostIps() {
		return is_array( $this->aPotentialHostIps ) ? $this->aPotentialHostIps : [];
	}

	/**
	 * @return string
	 */
	public function getLastSuccessfulSource() {
		return (string)$this->sLastSuccessfulSource;
	}

	/**
	 * @return string
	 */
	public function getPreferredSource() {
		return empty( $this->sPreferredSource ) ? self::DEFAULT_SOURCE : $this->sPreferredSource;
	}

	/**
	 * @param string[] $aPotentialHostIps
	 * @return $this
	 */
	public function setPotentialHostIps( $aPotentialHostIps ) {
		$this->aPotentialHostIps = $aPotentialHostIps;
		return $this;
	}

	/**
	 * @param string $sDefaultSource
	 * @return $this
	 */
	public function setPreferredSource( $sDefaultSource ) {
		$this->sPreferredSource = $sDefaultSource;
		return $this;
	}

	/**
	 * @return string[]
	 */
	private function getIpSourceOptions() {
		return array(
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_REAL_IP',
			'HTTP_X_SUCURI_CLIENTIP',
			'HTTP_INCAP_CLIENT_IP',
			'HTTP_X_SP_FORWARDED_IP',
			'HTTP_FORWARDED',
			'HTTP_CLIENT_IP'
		);
	}
}
