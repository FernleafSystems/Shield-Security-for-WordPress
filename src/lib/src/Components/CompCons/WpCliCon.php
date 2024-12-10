<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds\{
	ActivityLogPrint,
	BaseCmd,
	ConfigExport,
	ConfigImport,
	ConfigOptGet,
	ConfigOptSet,
	ConfigOptsList,
	CrowdsecDebug,
	CrowdsecSignals,
	DebugMode,
	ForceOff,
	IpRuleAdd,
	IpRuleRemove,
	IpRulesEnumerate,
	License,
	PluginReset,
	ScansRun,
	SecurityAdminAdd,
	SecurityAdminPin,
	SecurityAdminRemove};
use FernleafSystems\Wordpress\Services\Services;

class WpCliCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return Services::WpGeneral()->isWpCli();
	}

	protected function run() {
		add_action( 'cli_init', function () {
			try {
				\array_map(
					function ( $handlerClass ) {
						/** @var BaseCmd $handlerClass */
						( new $handlerClass() )->execute();
					},
					$this->enumCmdHandlers()
				);
			}
			catch ( \Exception $e ) {
			}
		} );
	}

	/**
	 * @return string[] - FQ class names
	 */
	protected function enumCmdHandlers() :array {
		return [
			ActivityLogPrint::class,
			ConfigOptsList::class,
			ConfigOptGet::class,
			ConfigOptSet::class,
			ConfigExport::class,
			ConfigImport::class,
			CrowdsecDebug::class,
			CrowdsecSignals::class,
			ForceOff::class,
			License::class,
			IpRuleAdd::class,
			IpRuleRemove::class,
			IpRulesEnumerate::class,
			PluginReset::class,
			ScansRun::class,
			SecurityAdminAdd::class,
			SecurityAdminRemove::class,
			SecurityAdminPin::class,
			DebugMode::class,
		];
	}
}