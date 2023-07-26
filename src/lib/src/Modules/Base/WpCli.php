<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\ModuleStandard;
use FernleafSystems\Wordpress\Services\Services;

class WpCli extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return Services::WpGeneral()->isWpCli()
			   && $this->getCfg()[ 'enabled' ]
			   && !empty( $this->getAllCmdHandlers() );
	}

	protected function isFeatureAvailable() :bool {
		return $this->con()->caps->canWpcliLevel2();
	}

	protected function run() {
		try {
			\array_map(
				function ( $handlerClass ) {
					return ( new $handlerClass() )
						->setMod( $this->mod() )
						->execute();
				},
				$this->getAllCmdHandlers()
			);
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @return string[]
	 */
	protected function getAllCmdHandlers() :array {
		$handlers = $this->enumCmdHandlers();
		if ( $this->getCfg()[ 'inc_mod_standard' ] ) {
			$handlers[] = ModuleStandard::class;
		}
		return $handlers;
	}

	/**
	 * @return string[] - FQ class names
	 */
	protected function enumCmdHandlers() :array {
		return [];
	}

	public function getCfg() :array {
		return \array_merge(
			[
				'enabled'          => false,
				'cmd_root'         => $this->con()->getPluginPrefix(),
				'cmd_base'         => $this->mod()->cfg->slug,
				'inc_mod_standard' => false,
			],
			$this->getOptions()->getRawData_FullFeatureConfig()[ 'wpcli' ] ?? []
		);
	}
}