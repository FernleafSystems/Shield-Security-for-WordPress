<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFileEntryFormatter extends BaseEntryFormatter {

	/**
	 * @return array
	 */
	protected function getBaseData() {
		$aData = parent::getBaseData();
		$oIt = $this->getResultItem();
		$aData[ 'path' ] = $oIt->path_fragment;
		$aData[ 'path_relabs' ] = Services::WpFs()->getPathRelativeToAbsPath( $oIt->path_full );
		$aData[ 'created_at' ] = $this->formatTimestampField( $this->getEntryVO()->created_at );
		$aData[ 'href_download' ] = $this->getMod()->createFileDownloadLink( $this->getEntryVO() );
		$aData[ 'custom_row' ] = false;
		return $aData;
	}
}
