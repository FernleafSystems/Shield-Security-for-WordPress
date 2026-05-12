<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
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

	protected ?HashVerificationResult $hashVerificationResult = null;

	public function isVerifiedHashTrustedSource() :bool {
		return $this->verifiedHashTrustedSource;
	}

	public function getHashVerificationResult() :HashVerificationResult {
		if ( !$this->hashVerificationResult instanceof HashVerificationResult ) {
			throw new \LogicException( 'Hash verification result has not been produced.' );
		}
		return $this->hashVerificationResult;
	}
}
