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
		$data = $this->getInsertData();

		if ( !Services::IP()->isValidIpOrRange( $data[ 'ip' ] ) ) {
			throw new \Exception( 'IP address provided is not valid' );
		}
		if ( empty( $data[ 'list' ] ) ) {
			throw new \Exception( 'An IP address must be assigned to a list' );
		}

		if ( strpos( $data[ 'ip' ], '/' ) !== false ) {
			$data[ 'is_range' ] = true;
		}

		return $this->setInsertData( $data );
	}
}