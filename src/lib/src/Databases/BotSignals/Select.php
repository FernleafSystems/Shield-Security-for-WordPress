<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

class Select extends Base\Select {

	use Common;

	/**
	 * @param string $ip
	 * @return EntryVO
	 */
	public function byIp( string $ip ) {
		return $this->filterByIP( inet_pton( $ip ) )
					->setResultsAsVo( true )
					->first();
	}

	/**
	 * @return string[]
	 */
	public function getDistinctIps() {
		return IpListSort::Sort( array_map(
			function ( $sIp ) {
				return inet_ntop( $sIp );
			},
			$this->getDistinctForColumn( 'ip' )
		) );
	}
}