<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

abstract class BasePluginThemeFile extends BaseScan {

	/**
	 * @var null|WpPluginVo|WpThemeVo
	 */
	protected $asset = null;

	protected bool $verifiedHashTrustedSource = false;

	public function isVerifiedHashTrustedSource() :bool {
		return $this->verifiedHashTrustedSource;
	}
}
