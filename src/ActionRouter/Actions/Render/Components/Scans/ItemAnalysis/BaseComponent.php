<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;

abstract class BaseComponent extends BaseScans {

	protected function getScanItem() :ResultItem {
		return $this->action_data[ 'scan_item' ];
	}

	protected function getRequiredDataKeys() :array {
		return [ 'scan_item' ];
	}
}