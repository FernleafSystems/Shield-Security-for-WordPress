<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;
	use Base\Traits\Select_IPTable;

	/**
	 * @param string $ip
	 * @return EntryVO
	 */
	public function byIp( string $ip ) {
		return $this->filterByIP( inet_pton( $ip ) )
					->setResultsAsVo( true )
					->first();
	}
}