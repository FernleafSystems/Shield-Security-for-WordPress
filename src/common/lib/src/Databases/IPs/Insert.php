<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

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

		if ( !Services::IP()->isValidIpOrRange( $aData[ 'ip' ] ) ) {
			throw new \Exception( 'IP address provided is not valid' );
		}
		if ( empty( $aData[ 'list' ] ) ) {
			throw new \Exception( 'An IP address must be assigned to a list' );
		}

		if ( strpos( $aData[ 'ip' ], '/' ) !== false ) {
			$aData[ 'is_range' ] = true;
		}

		return $this->setInsertData( $aData );
	}
}