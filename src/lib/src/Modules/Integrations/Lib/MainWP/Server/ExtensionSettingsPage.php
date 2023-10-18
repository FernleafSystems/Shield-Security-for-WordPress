<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModConsumer;

class ExtensionSettingsPage {

	use ExecOnce;
	use ModConsumer;

	protected function run() {

		add_filter( 'shield/custom_enqueue_assets', function ( array $assets, $hook ) {
			if ( $this->mod()->getControllerMWP()->isServerExtensionLoaded()
				 && 'mainwp_page_'.self::con()->mwpVO->extension->page === $hook ) {
				$assets[] = 'mainwp_server';
			}
			return $assets;
		}, 10, 2 );

		add_filter( 'shield/custom_localisations/components', function ( array $components, string $hook ) {
			$components[ 'mainwp_server' ] = [
				'key'     => 'mainwp_server',
				'handles' => [
					'mainwp_server',
				],
				'data'    => function () {
					return [
						'ajax' => [
						],
					];
				},
			];
			return $components;
		}, 10, 2 );
	}
}