<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\BackgroundProcessing\BackgroundProcess;

class ImportQueue extends BackgroundProcess {

	use PluginControllerConsumer;

	public function __construct() {
		parent::__construct( 'mfa_import_pages', self::con()->prefix() );
	}

	protected function task( $page ) {
		( new ImportController() )->processPage( (int)$page );
		return false;
	}

	protected function complete() {
		parent::complete();
		( new ImportController() )->markRunCompleted();
	}

	public function cleanupTransportState() :void {
		foreach ( $this->get_batches() as $batch ) {
			$this->delete( $batch->key );
		}

		\delete_site_option( $this->get_status_key() );
		\delete_site_transient( $this->identifier.'_process_lock' );
		$this->clear_scheduled_event();
	}
}
