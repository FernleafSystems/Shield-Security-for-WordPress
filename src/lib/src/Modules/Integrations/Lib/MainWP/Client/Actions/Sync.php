<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	HackGuard,
	Integrations\ModConsumer,
	Plugin
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use ModConsumer;

	private function isPermitted() :bool {
		return $this->opts()->isEnabledMainWP() && $this->con()->caps->canMainwpLevel1();
	}

	public function run() :array {
		return [
			'meta'    => $this->buildMetaData(),
			'modules' => $this->isPermitted() ? $this->buildModulesData() : [],
		];
	}

	private function buildMetaData() :array {
		$con = $this->con();
		return [
			'is_pro'       => $con->isPremiumActive(),
			'is_mainwp_on' => $this->isPermitted(),
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
		$data = [];
		foreach ( $this->con()->modules as $mod ) {
			$options = $this->opts()->getTransferableOptions();
			if ( !empty( $options ) ) {
				$data[ $mod->cfg->slug ] = [
					'options' => $options
				];

				switch ( $mod->cfg->slug ) {

					case Plugin\ModCon::SLUG:
						try {
							$data[ $mod->cfg->slug ][ 'grades' ] = [
								'integrity' => ( new Handler() )->getMeter( MeterSummary::class )
							];
						}
						catch ( \Exception $e ) {
						}
						break;

					case HackGuard\ModCon::SLUG:
						$data[ $mod->cfg->slug ][ 'scan_issues' ] = \array_filter(
							( new HackGuard\Scan\Results\Counts() )->all()
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