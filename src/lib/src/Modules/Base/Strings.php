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

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sOptKey ) );
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {
		throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
	}
}