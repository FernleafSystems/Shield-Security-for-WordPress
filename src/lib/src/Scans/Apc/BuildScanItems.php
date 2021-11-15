<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems extends Shield\Scans\Base\Utilities\BuildScanItems {

	public function run() :array {
		return Services::WpPlugins()->getInstalledPluginFiles();
	}
}