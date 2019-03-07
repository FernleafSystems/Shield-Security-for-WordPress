<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ChangeTracking;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @return array
	 */
	public function getInsertData() {
		$aInsert = parent::getInsertData();
//		$aInsert[ 'data' ] = \WP_Http_Encoding::compress( json_encode( $aInsert[ 'data' ] ) );
		return $aInsert;
	}
}