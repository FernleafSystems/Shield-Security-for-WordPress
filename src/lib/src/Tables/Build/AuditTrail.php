<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class AuditTrail
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class AuditTrail extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$aParams = $this->getParams();
		/** @var Shield\Databases\AuditTrail\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

		$oSelector->filterByEvent( $aParams[ 'fEvent' ] );

		$oIp = Services::IP();
		// If an IP is specified, it takes priority
		if ( $oIp->isValidIp( $aParams[ 'fIp' ] ) ) {
			$oSelector->filterByIp( $aParams[ 'fIp' ] );
		}
		elseif ( $aParams[ 'fExcludeYou' ] == 'Y' ) {
			$oSelector->filterByNotIp( $oIp->getRequestIp() );
		}

		/**
		 * put this date stuff in the base so we can filter anything
		 */
		if ( !empty( $aParams[ 'fDateFrom' ] ) && preg_match( '#^\d{4}-\d{2}-\d{2}$#', $aParams[ 'fDateFrom' ] ) ) {
			$aParts = explode( '-', $aParams[ 'fDateFrom' ] );
			$sTs = Services::Request()->carbon()
						   ->setDate( $aParts[ 0 ], $aParts[ 1 ], $aParts[ 2 ] )
						   ->setTime( 0, 0 )
				->timestamp;
			$oSelector->filterByCreatedAt( $sTs, '>' );
		}

		if ( !empty( $aParams[ 'fDateTo' ] ) && preg_match( '#^\d{4}-\d{2}-\d{2}$#', $aParams[ 'fDateTo' ] ) ) {
			$aParts = explode( '-', $aParams[ 'fDateTo' ] );
			$sTs = Services::Request()->carbon()
						   ->setDate( $aParts[ 0 ], $aParts[ 1 ], $aParts[ 2 ] )
						   ->setTime( 0, 0 )
						   ->addDay()
				->timestamp;
			$oSelector->filterByCreatedAt( $sTs, '<' );
		}

		// if username is provided, this takes priority over "logged-in" (even if it's invalid)
		if ( !empty( $aParams[ 'fUsername' ] ) ) {
			$oSelector->filterByUsername( $aParams[ 'fUsername' ] );
		}
		elseif ( $aParams[ 'fLoggedIn' ] >= 0 ) {
			$oSelector->filterByIsLoggedIn( $aParams[ 'fLoggedIn' ] );
		}

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
			'fEvent'      => '',
			'fLoggedIn'   => -1,
			'fExcludeYou' => '',
			'fDateFrom'   => '',
			'fDateTo'     => '',
		];
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = [];

		$sYou = Services::IP()->getRequestIp();
		$oCon = $this->getCon();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\AuditTrail\EntryVO $oEntry */

			$sMsg = 'Audit message could not be retrieved';
			if ( empty( $oEntry->message ) ) {
				/**
				 * To cater for the contexts that don't refer to a module, but rather a context
				 * with the Audit Trail module
				 */
				$oModule = $oCon->getModule( $oEntry->context );
				if ( empty( $oModule ) ) {
					$oModule = $oCon->getModule_AuditTrail();
				}
				$oStrings = $oModule->getStrings();

				if ( $oStrings instanceof Shield\Modules\Base\Strings ) {
					$sMsg = stripslashes( sanitize_textarea_field(
						vsprintf(
							implode( "\n", $oStrings->getAuditMessage( $oEntry->event ) ),
							$oEntry->meta
						)
					) );
				}
			}
			else {
				$sMsg = $oEntry->message;
			}

			if ( !isset( $aEntries[ $oEntry->rid ] ) ) {
				$aE = $oEntry->getRawDataAsArray();
				$aE[ 'meta' ] = $oEntry->meta;
				$aE[ 'event' ] = str_replace( '_', ' ', sanitize_text_field( $oEntry->event ) );
				$aE[ 'message' ] = $sMsg;
				$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
				if ( $oEntry->ip == $sYou ) {
					$aE[ 'your_ip' ] = '<small> ('.__( 'You', 'wp-simple-firewall' ).')</small>';
				}
				else {
					$aE[ 'your_ip' ] = '';
				}
				if ( $oEntry->wp_username == '-' ) {
					$aE[ 'wp_username' ] = __( 'Not logged-in', 'wp-simple-firewall' );
				}
			}
			else {
				$aE = $aEntries[ $oEntry->rid ];
				$aE[ 'message' ] .= "\n".$sMsg;
				$aE[ 'category' ] = max( $aE[ 'category' ], $oEntry->category );
			}

			$aEntries[ $oEntry->rid ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\AuditTrail
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\AuditTrail();
	}
}