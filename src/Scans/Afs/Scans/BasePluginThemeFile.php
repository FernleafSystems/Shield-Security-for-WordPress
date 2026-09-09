<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetFileContext,
	HashVerificationResult
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\AssetTrustState;

abstract class BasePluginThemeFile extends BaseScan {

	protected ?HashVerificationResult $hashVerificationResult = null;

	protected ?AssetFileContext $assetContext = null;

	private ?AssetTrustState $assetTrustState = null;

	public function setAssetContext( AssetFileContext $assetContext ) :self {
		$this->assetContext = $assetContext;
		return $this;
	}

	public function setAssetTrustState( AssetTrustState $assetTrustState ) :self {
		$this->assetTrustState = $assetTrustState;
		return $this;
	}

	public function getHashVerificationResult() :?HashVerificationResult {
		return $this->hashVerificationResult;
	}

	protected function getAssetContext() :AssetFileContext {
		if ( !$this->assetContext instanceof AssetFileContext ) {
			throw new \LogicException( 'Asset file context has not been produced.' );
		}
		return $this->assetContext;
	}

	protected function getAssetTrustState() :AssetTrustState {
		if ( !$this->assetTrustState instanceof AssetTrustState ) {
			throw new \LogicException( 'Asset trust state has not been provided.' );
		}
		return $this->assetTrustState;
	}
}
