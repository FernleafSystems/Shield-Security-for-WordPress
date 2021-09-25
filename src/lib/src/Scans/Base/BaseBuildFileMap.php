<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ScanActionVO;

abstract class BaseBuildFileMap extends Shield\Scans\Base\Utilities\BuildScanItems {

	use ScanActionConsumer;

	public function run() :array {
		return $this->build();
	}

	abstract public function build() :array;

	protected function isAutoFilterFile( \SplFileInfo $file ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isAutoFilterResults() && $file->getSize() === 0;
	}

	protected function isWhitelistedPath( string $path ) :bool {
		$whitelisted = false;

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		foreach ( $action->paths_whitelisted as $wlPathRegEx ) {
			if ( preg_match( $wlPathRegEx, $path ) ) {
				$whitelisted = true;
				break;
			}
		}
		return $whitelisted;
	}
}