<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

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

		if ( empty( $aData[ 'ip' ] ) || !Services::IP()->isValidIp( $aData[ 'ip' ] ) ) {
			throw new \Exception( 'IP address provided is not valid' );
		}

		return $this->setInsertData( $aData );
	}
}