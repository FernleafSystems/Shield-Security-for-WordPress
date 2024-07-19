<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BuildScanAction {

	use PluginControllerConsumer;
	use ScanActionConsumer;

	/**
	 * @return static
	 */
	public function build() {
		$this->setCustomFields();
		$this->buildScanItems();
		$this->setStandardFields();
		return $this;
	}

	abstract protected function buildScanItems();

	protected function setStandardFields() {
		$action = $this->getScanActionVO();
		if ( empty( $action->created_at ) ) {
			$action->created_at = Services::Request()->ts();
			$action->finished_at = 0;
			$action->usleep = (int)( 1000000*max( 0, apply_filters(
					self::con()->prefix( 'scan_block_sleep' ),
					$action::DEFAULT_SLEEP_SECONDS, $action->scan
				) ) );
			$action->site_assets = $this->siteAssetsSnap();
		}
	}

	protected function siteAssetsSnap() :array {
		return [
			'wp'      => [
				'version' => Services::WpGeneral()->getVersion(),
			],
			'plugins' => \array_filter( \array_map(
				function ( $plugin ) {
					return $plugin->active ? $plugin->Version : null;
				},
				Services::WpPlugins()->getPluginsAsVo()
			) ),
			'themes'  => \array_filter( \array_map(
				function ( $theme ) {
					return $theme->active ? $theme->Version : null;
				},
				Services::WpThemes()->getThemesAsVo()
			) ),
		];
	}

	protected function setCustomFields() {
	}
}