<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

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

		if ( isset( $data[ 'message' ] ) && is_array( $data[ 'message' ] ) ) {
			$data[ 'message' ] = implode( ' ', $data[ 'message' ] );
		}
		if ( isset( $data[ 'data' ] ) && !is_string( $data[ 'data' ] ) ) {
			$data[ 'data' ] = '';
		}
		if ( empty( $data[ 'ip' ] ) || !Services::IP()->isValidIp( $data[ 'ip' ] ) ) {
			$data[ 'ip' ] = '';
		}

		return $this->setInsertData( $data );
	}
}