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

		$aData = $this->getInsertData();

		if ( is_array( $aData[ 'message' ] ) ) {
			$aData[ 'message' ] = implode( ' ', $aData[ 'message' ] );
		}
		if ( isset( $aData[ 'data' ] ) && !is_string( $aData[ 'data' ] ) ) {
			$aData[ 'data' ] = '';
		}

		return $this->setInsertData( $aData );
	}
}