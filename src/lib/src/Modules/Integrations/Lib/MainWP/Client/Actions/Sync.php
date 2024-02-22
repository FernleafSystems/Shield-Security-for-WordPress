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
		return $this->opts()->isEnabledMainWP() && self::con()->caps->canMainwpLevel1();
	}

	public function run() :array {
		return [
			'meta'    => $this->buildMetaData(),
			'modules' => $this->isPermitted() ? $this->buildModulesData() : [],
		];
	}

	private function buildMetaData() :array {
		$con = self::con();
		return [
			'is_pro'       => $con->isPremiumActive(),
			'is_mainwp_on' => $this->isPermitted(),
			'installed_at' => $con->getModule_Plugin()->getInstallDate(),
			'sync_at'      => Services::Request()->ts(),
			'version'      => $con->cfg->version(),
			'has_update'   => Services::WpPlugins()->isUpdateAvailable( $con->base_file ),
		];
	}

	/**
	 * @return array[]
	 */
	private function buildModulesData() :array {
		$data = [];

		$exportedOptions = ( new Plugin\Lib\ImportExport\Export() )->getFullTransferableOptionsExport();

		foreach ( self::con()->modules as $mod ) {
			$data[ $mod->cfg->slug ] = [
				'options' => $exportedOptions[ $mod->cfg->slug ],
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
		return $data;
	}
}