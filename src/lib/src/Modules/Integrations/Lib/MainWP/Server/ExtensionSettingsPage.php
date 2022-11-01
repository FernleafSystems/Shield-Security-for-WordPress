<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class ExtensionSettingsPage extends ExecOnceModConsumer {

	protected function run() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues, $hook ) {
			$con = $this->getCon();
			if ( $con->getModule_Integrations()->getControllerMWP()->isServerExtensionLoaded()
				 && 'mainwp_page_'.$con->mwpVO->extension->page === $hook ) {

				$enqueues[ Enqueue::JS ][] = 'shield/integrations/mainwp-server';
				$enqueues[ Enqueue::CSS ][] = 'shield/integrations/mainwp-server';

//				$handle = 'semantic-ui-datatables-select';
//				wp_register_script(
//					$handle,
//					'https://cdn.datatables.net/select/1.3.1/js/dataTables.select.min.js',
//					[ 'semantic-ui-datatables' ],
//					$con->getVersion(),
//					true
//				);
//				wp_enqueue_script( 'semantic-ui-datatables-select' );
//				wp_register_style(
//					$handle,
//					'https://cdn.datatables.net/select/1.3.1/css/select.dataTables.min.css',
//					[ 'semantic-ui-datatables' ],
//					$con->getVersion()
//				);
//				wp_enqueue_style( 'semantic-ui-datatables-select' );
			}
			return $enqueues;
		}, 10, 2 );
	}
}