<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use PluginControllerConsumer;

	private function isPermitted() :bool {
		return self::con()->comps->opts_lookup->enabledIntegrationMainwp() && self::con()->caps->canMainwpLevel1();
	}

	public function run() :array {
		$con = self::con();
		return [
			'meta'        => [
				'is_pro'       => $con->isPremiumActive(),
				'is_mainwp_on' => $this->isPermitted(),
				'installed_at' => $con->comps->opts_lookup->getInstalledAt(),
				'sync_at'      => Services::Request()->ts(),
				'version'      => $con->cfg->version(),
				'has_update'   => Services::WpPlugins()->isUpdateAvailable( $con->base_file ),
			],
			'options'     => [], // not necessary yet ( new ImportExport\Export() )->getFullTransferableOptionsExport(),
			'integrity'   => \array_intersect_key(
				( new MeterAnalysis\Handler() )->getMeter( MeterAnalysis\Meter\MeterSummary::class ),
				\array_flip( [ 'status', 'totals' ] )
			),
			'scan_issues' => ( new Counts() )->all(),
		];
	}
}