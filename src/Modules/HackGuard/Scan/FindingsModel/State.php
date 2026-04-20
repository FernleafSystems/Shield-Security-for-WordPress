<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\FindingsModel;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class State {

	use PluginControllerConsumer;

	public const LEGACY = 'legacy';
	public const READY = 'ready';
	public const RECONCILING = 'reconciling';

	public function current() :string {
		$state = (string)self::con()->opts->optGet( 'scan_findings_model_state' );
		if ( !\in_array( $state, [ self::LEGACY, self::READY, self::RECONCILING ], true ) ) {
			$state = self::READY;
		}
		return $state;
	}

	public function hasLegacyRows() :bool {
		return self::con()->db_con->scan_result_items->isReady()
			   && (int)Services::WpDb()->getVar(
				   sprintf(
					   "SELECT COUNT(*) FROM `%s` WHERE `scan`='';",
					   self::con()->db_con->scan_result_items->getTable()
				   )
			   ) > 0;
	}

	public function isReady() :bool {
		return $this->current() === self::READY;
	}

	public function isReconciling() :bool {
		return $this->current() === self::RECONCILING;
	}

	public function markReady() :void {
		self::con()->opts->optSet( 'scan_findings_model_state', self::READY )->store();
	}

	public function prepareUpgrade() :void {
		if ( !$this->hasLegacyRows() ) {
			$this->markReady();
		}
		else {
			self::con()->opts->optSet( 'scan_findings_model_state', self::LEGACY )->store();
		}
	}

	public function startReconciling() :void {
		if ( $this->isReady() ) {
			return;
		}

		$this->failActiveRuns();
		self::con()->opts->optSet( 'scan_findings_model_state', self::RECONCILING )->store();
	}

	private function failActiveRuns() :void {
		( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState() )->markUnfinishedRunsFailed();
	}
}
