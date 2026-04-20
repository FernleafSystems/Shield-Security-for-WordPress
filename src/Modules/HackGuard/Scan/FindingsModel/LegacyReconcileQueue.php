<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\FindingsModel;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\BackgroundProcessing\BackgroundProcess;

class LegacyReconcileQueue extends BackgroundProcess {

	use PluginControllerConsumer;

	public function __construct() {
		parent::__construct( 'scan_findings_reconcile', self::con()->prefix() );
	}

	protected function complete() {
		parent::complete();
		( new LegacyReconcile() )->finishIfComplete();
	}

	protected function get_batch() {
		$ids = ( new LegacyReconcile() )->nextLegacyIDs();

		$batch = new \stdClass();
		$batch->key = empty( $ids ) ? 'empty' : (string)\reset( $ids );
		$batch->data = $ids;
		return $batch;
	}

	protected function is_queue_empty() {
		return !( new LegacyReconcile() )->hasLegacyRows();
	}

	public function save() {
		return $this;
	}

	protected function task( $resultItemID ) {
		( new LegacyReconcile() )->reconcileById( (int)$resultItemID );
		return false;
	}

	public function update( $key, $data ) {
		return $this;
	}
}
