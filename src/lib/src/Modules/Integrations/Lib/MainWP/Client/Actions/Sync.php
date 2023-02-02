<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Plugin,
	HackGuard
};
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use ModConsumer;

	public function run() :array {
		$con = $this->getCon();
		/** @var Options $intOpts */
		$intOpts = $con->getModule_Integrations()->getOptions();
		return [
			'meta'    => $this->buildMetaData(),
			'modules' => ( $con->isPremiumActive() && $intOpts->isEnabledMainWP() ) ? $this->buildModulesData() : [],
		];
	}

	private function buildMetaData() :array {
		$con = $this->getCon();
		/** @var Options $intOpts */
		$intOpts = $con->getModule_Integrations()->getOptions();
		return [
			'is_pro'       => $con->isPremiumActive(),
			'is_mainwp_on' => $con->isPremiumActive() && $intOpts->isEnabledMainWP(),
			'installed_at' => $con->getModule_Plugin()->getInstallDate(),
			'sync_at'      => Services::Request()->ts(),
			'version'      => $con->getVersion(),
			'has_update'   => Services::WpPlugins()->isUpdateAvailable( $con->base_file ),
		];
	}

	/**
	 * @return array[]
	 */
	private function buildModulesData() :array {
		$con = $this->getCon();
		$data = [];
		foreach ( $con->modules as $mod ) {
			$options = $this->getOptions()->getTransferableOptions();
			if ( !empty( $options ) ) {
				$data[ $mod->cfg->slug ] = [
					'options' => $options
				];

				switch ( $mod->cfg->slug ) {

					case Plugin\ModCon::SLUG:
						try {
							$data[ $mod->cfg->slug ][ 'grades' ] = [
								'integrity' => ( new Handler() )
									->setCon( $this->getCon() )
									->getMeter( MeterSummary::class )
							];
						}
						catch ( \Exception $e ) {
						}
						break;

					case HackGuard\ModCon::SLUG:
						$data[ $mod->cfg->slug ][ 'scan_issues' ] = array_filter(
							( new HackGuard\Scan\Results\Counts() )
								->setMod( $mod )
								->all()
						);
						break;

					default:
						break;
				}
			}
		}
		return $data;
	}
}