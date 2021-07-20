<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;

abstract class BaseBuildFileMap {

	use Shield\Modules\ModConsumer;
	use ScanActionConsumer;

	abstract public function build() :array;

	protected function isAutoFilterFile( \SplFileInfo $file ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isAutoFilterResults()
			   && $file->getSize() === 0;
	}
}