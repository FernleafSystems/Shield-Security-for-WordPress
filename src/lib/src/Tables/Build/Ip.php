<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

class Ip extends BaseBuild {

	/**
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$params = $this->getParams();

		/** @var IPs\Select $selector */
		$selector = $this->getWorkingSelector();
		$selector->filterByLists( $params[ 'fLists' ] );
		if ( Services::IP()->isValidIp( $params[ 'fIp' ] ) ) {
			$selector->filterByIp( $params[ 'fIp' ] );
		}

		$selector->setOrderBy( 'last_access_at', 'DESC', true );
		$selector->setOrderBy( 'created_at', 'DESC', false );

		return $this;
	}

	protected function getCustomParams() :array {
		return [
			'fLists' => '',
			'fIp'    => '',
		];
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$srvIP = Services::IP();

		$nTransLimit = $opts->getOffenseLimit();
		$you = $this->getCon()->this_req->ip;
		$entries = [];

		foreach ( $this->getEntriesRaw() as $key => $entry ) {
			/** @var IPs\EntryVO $entry */
			$e = $entry->getRawData();
			$bBlocked = $entry->blocked_at > 0 || $entry->transgressions >= $nTransLimit;
			$e[ 'last_trans_at' ] = Services::Request()
											 ->carbon( true )
											 ->setTimestamp( $entry->last_access_at )
											 ->diffForHumans();
			$e[ 'last_access_at' ] = $this->formatTimestampField( $entry->last_access_at );
			$e[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
			$e[ 'blocked' ] = $bBlocked ? __( 'Yes' ) : __( 'No' );
			$e[ 'expires_at' ] = $this->formatTimestampField( $entry->last_access_at + $opts->getAutoExpireTime() );
			$e[ 'is_you' ] = $srvIP->checkIp( $you, $entry->ip );
			$e[ 'ip' ] = sprintf( '%s%s',
				$this->getIpAnalysisLink( $entry->ip ),
				$e[ 'is_you' ] ? ' <span class="small">('.__( 'You', 'wp-simple-firewall' ).')</span>' : ''
			);

			$entries[ $key ] = $e;
		}
		return $entries;
	}

	/**
	 * @return Tables\Render\WpListTable\IpBlack|Tables\Render\WpListTable\IpWhite
	 */
	protected function getTableRenderer() {
		$aLists = $this->getParams()[ 'fLists' ];
		if ( empty( $aLists ) || in_array( ModCon::LIST_MANUAL_WHITE, $aLists ) ) {
			$sTable = new Tables\Render\WpListTable\IpWhite();
		}
		else {
			$sTable = new Tables\Render\WpListTable\IpBlack();
		}
		return $sTable;
	}
}