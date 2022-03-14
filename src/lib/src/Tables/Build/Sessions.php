<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

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
		$aEntries = [];

		$srvIP = Services::IP();
		$you = $srvIP->getRequestIp();
		foreach ( $this->getEntriesRaw() as $nKey => $entry ) {
			/** @var Session\EntryVO $entry */
			$aE = $entry->getRawData();
			$aE[ 'is_secadmin' ] = $this->isSecAdminSession( $entry ) ? __( 'Yes' ) : __( 'No' );
			$aE[ 'last_activity_at' ] = $this->formatTimestampField( $entry->last_activity_at );
			$aE[ 'logged_in_at' ] = $this->formatTimestampField( $entry->logged_in_at );

			try {
				$aE[ 'is_you' ] = $srvIP->checkIp( $you, $entry->ip );
			}
			catch ( \Exception $e ) {
				$aE[ 'is_you' ] = false;
			}
			$aE[ 'ip' ] = sprintf( '%s%s',
				$this->getIpAnalysisLink( $entry->ip ),
				$aE[ 'is_you' ] ? ' <small>('.__( 'You', 'wp-simple-firewall' ).')</small>' : ''
			);

			$oWpUsers = Services::WpUsers();
			$aE[ 'wp_username' ] = sprintf(
				'<a href="%s">%s</a>',
				$oWpUsers->getAdminUrl_ProfileEdit( $oWpUsers->getUserByUsername( $aE[ 'wp_username' ] ?? '' ) ),
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