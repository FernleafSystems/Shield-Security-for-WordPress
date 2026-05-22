<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetFileContext,
	HashVerificationResult
};

abstract class BasePluginThemeFile extends BaseScan {

	protected ?HashVerificationResult $hashVerificationResult = null;

	protected ?AssetFileContext $assetContext = null;

	public function setAssetContext( AssetFileContext $assetContext ) :self {
		$this->assetContext = $assetContext;
		return $this;
	}

	public function getHashVerificationResult() :HashVerificationResult {
		if ( !$this->hashVerificationResult instanceof HashVerificationResult ) {
			throw new \LogicException( 'Hash verification result has not been produced.' );
		}
		return $this->hashVerificationResult;
	}

	protected function getAssetContext() :AssetFileContext {
		if ( !$this->assetContext instanceof AssetFileContext ) {
			throw new \LogicException( 'Asset file context has not been produced.' );
		}
		return $this->assetContext;
	}
}
