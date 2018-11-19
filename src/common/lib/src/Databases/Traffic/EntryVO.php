<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;

/**
 * Class EntryVO
 * @property string rid
 * @property int    uid
 * @property string ip
 * @property string path
 * @property string code
 * @property string ua
 * @property string verb
 * @property bool   trans
 */
class EntryVO extends Databases\Base\BaseEntryVO {

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {
		switch ( $sProperty ) {

			case 'ip':
				$mVal = inet_ntop( parent::__get( $sProperty ) );
				break;

			default:
				$mVal = parent::__get( $sProperty );
		}
		return $mVal;
	}
}