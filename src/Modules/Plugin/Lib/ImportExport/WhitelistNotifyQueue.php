<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\BackgroundProcessing\BackgroundProcess;

class WhitelistNotifyQueue extends BackgroundProcess {

	use PluginControllerConsumer;

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @inheritDoc
	 */
	protected function task( $item ) {
		$targetUrl = self::con()->plugin_urls->noncedPluginAction( PluginImportExport_UpdateNotified::class, $item );
		$targetHost = (string)( \wp_parse_url( $targetUrl, \PHP_URL_HOST ) ?: '' );
		$allowTargetHost = static fn( $external, $host ) :bool => ( $targetHost !== '' && \strcasecmp( (string)$host, $targetHost ) === 0 ) || $external;

		add_filter( 'http_request_host_is_external', $allowTargetHost, 11, 2 );
		try {
			Services::HttpRequest()->get( $targetUrl );
		}
		finally {
			remove_filter( 'http_request_host_is_external', $allowTargetHost, 11 );
		}
		return false;
	}

	/**
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		self::con()->comps->events->fireEvent( 'import_notify_sent' );
	}
}
