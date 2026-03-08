<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ContainerRenderer {

	use PluginControllerConsumer;

	public function render( string $ip ) :string {
		return self::con()->action_router->render(
			Container::class,
			$this->buildActionData( $ip )
		);
	}

	/**
	 * @return array{ip:string}
	 */
	private function buildActionData( string $ip ) :array {
		return [
			'ip' => $ip,
		];
	}
}
