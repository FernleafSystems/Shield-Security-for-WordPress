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
		return self::con()->caps->canWpcliLevel2();
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
		$handlers[] = ModuleStandard::class;
		return $handlers;
	}

	/**
	 * @return string[] - FQ class names
	 */
	protected function enumCmdHandlers() :array {
		return [];
	}

	public function getCfg() :array {
		return \array_merge( [
			'enabled'  => true,
			'cmd_root' => 'shield',
			'cmd_base' => $this->mod()->cfg->slug,
		], $this->mod()->cfg->properties[ 'wpcli' ] ?? [] );
	}
}