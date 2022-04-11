<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class NotifyWhitelist extends ExecOnceModConsumer {

	protected function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !empty( $opts->getImportExportWhitelist() ) ) {

			foreach ( $opts->getImportExportWhitelist() as $url ) {
				Services::HttpRequest()->get(
					add_query_arg( [ 'shield_action' => 'importexport_updatenotified' ], $url ),
					[ 'blocking' => false ]
				);
			}

			$this->getCon()->fireEvent( 'import_notify_sent' );
		}
	}
}
