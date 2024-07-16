<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\CrowdSourced;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Utilities\Constants\Regex;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Submit\{
	PreSubmit,
	Submit
};

class SubmitHashes {

	use PluginControllerConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $asset;

	/**
	 * @var string[]
	 */
	private $hashes;

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	public function run( $asset ) {
		$this->asset = $asset;

		if ( $this->canSubmitAsset() ) {
			$this->hashes = ( new Build\BuildHashesForCrowdSource() )
				->build( $asset, self::con()->cfg->configuration->def( 'file_scan_extensions' ) );

			if ( !empty( $this->hashes ) && $this->isSubmitRequired() ) {
				$this->submit();
			}
		}
	}

	private function canSubmitAsset() :bool {
		return self::con()->is_mode_live
			   && \preg_match( sprintf( '#^%s$#', Regex::ASSET_VERSION ), (string)$this->asset->Version )
			   && \preg_match( sprintf( '#^%s$#', Regex::ASSET_SLUG ), (string)$this->asset->slug );
	}

	private function isSubmitRequired() :bool {
		$response = ( new PreSubmit() )
			->setHashes( $this->hashes )
			->query();
		return \is_array( $response ) && !empty( $response[ 'hashes' ] ) && $response[ 'hashes' ][ 'submit_required' ];
	}

	private function submit() :bool {
		$sub = ( new Submit() )->setHashes( $this->hashes );
		$response = $this->asset->asset_type === 'plugin' ? $sub->submitPlugin( $this->asset ) : $sub->submitTheme( $this->asset );
		return !empty( $response ) && empty( $response[ 'error' ] );
	}
}