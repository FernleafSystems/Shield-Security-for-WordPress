<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\{
	ImportExport,
	MeterAnalysis
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use PluginControllerConsumer;

	private function isPermitted() :bool {
		return self::con()->comps->opts_lookup->enabledIntegrationMainwp() && self::con()->caps->canMainwpLevel1();
	}

	public function run() :array {
		$additional = $this->isPermitted() ? [
			'options'     => ( new ImportExport\Export() )->getFullTransferableOptionsExport(),
			'integrity'   => ( new MeterAnalysis\Handler() )->getMeter( MeterAnalysis\Meter\MeterSummary::class ),
			'scan_issues' => ( new Counts() )->all(),
		] : [];
		return \array_merge( [
			'meta'    => $this->buildMetaData(),
			/** @description 19.1 - remove the modules as it's old structure */
			'modules' => $additional,
		], $additional );
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
}