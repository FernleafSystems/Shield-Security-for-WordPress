<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class NotifyWhitelist extends ExecOnceModConsumer {

	protected function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !empty( $opts->getImportExportWhitelist() ) ) {

			foreach ( $opts->getImportExportWhitelist() as $url ) {
				Services::HttpRequest()->get(
					$this->getCon()->plugin_urls->noncedPluginAction( PluginImportExport_UpdateNotified::SLUG, $url ),
					[ 'blocking' => false ]
				);
			}

			$this->getCon()->fireEvent( 'import_notify_sent' );
		}
	}
}
