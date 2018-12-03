<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		parent::verifyInsertData();

		$aData = $this->getInsertData();
		if ( !isset( $aData[ 'ip' ] ) ) {
			$aData[ 'ip' ] = Services::IP()->getRequestIp();
		}

		return $this->setInsertData( $aData );
	}
}