<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\ModuleStandard;

class WpCli extends ExecOnceModConsumer {

	protected function run() {
		try {
			foreach ( $this->getAllCmdHandlers() as $handler ) {
				$handler->execute();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @return WpCli[]
	 */
	protected function getAllCmdHandlers() :array {
		return array_map(
			function ( $handler ) {
				return $handler->setMod( $this->getMod() );
			},
			array_merge(
				[ new ModuleStandard() ],
				$this->getCmdHandlers()
			)
		);
	}

	/**
	 * @return WpCli[]
	 */
	protected function getCmdHandlers() :array {
		return [];
	}
}