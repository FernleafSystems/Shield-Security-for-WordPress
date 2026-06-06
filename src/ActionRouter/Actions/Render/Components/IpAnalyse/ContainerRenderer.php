<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ContainerRenderer {

	use PluginControllerConsumer;

	public function render( string $ip, bool $renderInlineTabs = false ) :string {
		return ( new SectionRenderFallback() )->render(
			Container::class,
			$this->buildActionData( $ip, $renderInlineTabs ),
			__( 'IP Analysis', 'wp-simple-firewall' )
		);
	}

	/**
	 * @return array{ip:string,render_inline_tabs:bool}
	 */
	private function buildActionData( string $ip, bool $renderInlineTabs ) :array {
		return [
			'ip'                 => $ip,
			'render_inline_tabs' => $renderInlineTabs,
		];
	}
}
