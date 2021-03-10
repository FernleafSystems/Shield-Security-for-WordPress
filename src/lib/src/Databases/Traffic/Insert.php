<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Insert extends Base\Insert {

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		parent::verifyInsertData();

		$data = $this->getInsertData();
		if ( empty( $data[ 'ip' ] ) ) {
			throw new \Exception( 'IP address provided is not valid' );
		}

		return $this->setInsertData( $data );
	}
}