<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Sessions
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class Sessions extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		/** @var Session\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

		$aParams = $this->getParams();

		// If an IP is specified, it takes priority
		if ( Services::IP()->isValidIp( $aParams[ 'fIp' ] ) ) {
			$oSelector->filterByIp( $aParams[ 'fIp' ] );
		}

		if ( !empty( $aParams[ 'fUsername' ] ) ) {
			$oUser = Services::WpUsers()->getUserByUsername( $aParams[ 'fUsername' ] );
			if ( !empty( $oUser ) ) {
				$oSelector->filterByUsername( $oUser->user_login );
			}
		}

		$oSelector->setOrderBy( 'last_activity_at' );

		return $this;
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
		return array(
			'fIp'       => '',
			'fUsername' => '',
		);
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		$sYou = Services::IP()->getRequestIp();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Session\EntryVO $oEntry */
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'is_secadmin' ] = ( $oEntry->getSecAdminAt() > 0 ) ? __( 'Yes' ) : __( 'No' );
			$aE[ 'last_activity_at' ] = $this->formatTimestampField( $oEntry->last_activity_at );
			$aE[ 'logged_in_at' ] = $this->formatTimestampField( $oEntry->logged_in_at );
			if ( $oEntry->ip == $sYou ) {
				$aE[ 'your_ip' ] = '<small> ('._wpsf__( 'You' ).')</small>';
			}
			else {
				$aE[ 'your_ip' ] = '';
			}
			$aEntries[ $nKey ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\Sessions
	 */
	protected function getTableRenderer() {
		return new Tables\Render\Sessions();
	}
}