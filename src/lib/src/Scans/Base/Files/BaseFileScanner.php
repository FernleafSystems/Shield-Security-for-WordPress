<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class BaseFileScanner {

	use Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
	use Shield\Modules\ModConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param string $fullPath
	 * @return Shield\Scans\Base\ResultItem|null
	 */
	abstract public function scan( string $fullPath );
}