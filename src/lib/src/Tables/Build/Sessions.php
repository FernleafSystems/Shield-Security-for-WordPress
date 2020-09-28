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
	 * @var string[]
	 */
	private $aSecAdminUsers;

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
		$modInsights = $this->getCon()->getModule_Insights();
		$aEntries = [];

		$sYou = Services::IP()->getRequestIp();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Session\EntryVO $oEntry */
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'is_secadmin' ] = $this->isSecAdminSession( $oEntry ) ? __( 'Yes' ) : __( 'No' );
			$aE[ 'last_activity_at' ] = $this->formatTimestampField( $oEntry->last_activity_at );
			$aE[ 'logged_in_at' ] = $this->formatTimestampField( $oEntry->logged_in_at );
			if ( $oEntry->ip == $sYou ) {
				$aE[ 'is_you' ] = '<small> ('.__( 'You', 'wp-simple-firewall' ).')</small>';
			}
			else {
				$aE[ 'is_you' ] = '';
			}
			$aE[ 'ip' ] = sprintf( '%s%s',
				$this->getIpAnalysisLink( $oEntry->ip ),
				$aE[ 'is_you' ] ? ' <span class="small">('.__( 'You', 'wp-simple-firewall' ).')</span>' : ''
			);

			$oWpUsers = Services::WpUsers();
			$aE[ 'wp_username' ] = sprintf(
				'<a href="%s">%s</a>',
				$oWpUsers->getAdminUrl_ProfileEdit( $oWpUsers->getUserByUsername( $aE[ 'wp_username' ] ) ),
				$aE[ 'wp_username' ]
			);
			$aEntries[ $nKey ] = $aE;
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