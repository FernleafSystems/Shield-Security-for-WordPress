<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Insert extends Base\Insert {

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		parent::verifyInsertData();

		$aData = $this->getInsertData();
		if ( !is_string( $aData[ 'data' ] ) || strpos( $aData[ 'data' ], '{' ) === false ) {
			$aData[ 'data' ] = json_encode( $aData[ 'data' ] );
		}

		return $this->setInsertData( $aData );
	}
}