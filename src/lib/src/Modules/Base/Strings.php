<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Strings {

	use ModConsumer;

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [];
	}

	/**
	 * @param string $sKey
	 * @return string[]
	 */
	public function getAuditMessage( $sKey ) {
		$aMsg = $this->getAuditMessages();
		return isset( $aMsg[ $sKey ] ) ? $aMsg[ $sKey ] : [];
	}
}