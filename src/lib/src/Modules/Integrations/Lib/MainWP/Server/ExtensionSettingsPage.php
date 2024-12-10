<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ExtensionSettingsPage {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {

		add_filter( 'shield/custom_enqueue_assets', function ( array $assets, $hook ) {
			if ( self::con()->comps->mainwp->isServerExtensionLoaded()
				 && 'mainwp_page_'.self::con()->mwpVO->extension->page === $hook ) {
				$assets[] = 'mainwp_server';
			}
			return $assets;
		}, 10, 2 );

		add_filter( 'shield/custom_localisations/components', function ( array $components ) {
			$components[ 'mainwp_server' ] = [
				'key'     => 'mainwp_server',
				'handles' => [
					'mainwp_server',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'site_action' => ActionData::Build( MainWP\ServerActions\MainwpServerClientActionHandler::class ),
							'ext_table'   => ActionData::Build( MainWP\MainwpExtensionTableSites::class ),
						],
					];
				},
			];
			return $components;
		} );
	}
}