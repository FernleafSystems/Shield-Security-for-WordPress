<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueInit;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class QueueBuilder extends Utilities\BackgroundProcessing\BackgroundProcess {

	use PluginControllerConsumer;

	protected $cron_interval = 1;

	public function __construct() {
		parent::__construct( 'shield_scanqbuild', self::con()->getPluginPrefix( '_' ) );
	}

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		$scan = self::con()->db_con->scans->getQuerySelector()
					->filterByStatus( 'queued' )
					->filterByNotFinished()
					->setOrderBy( 'created_at', 'ASC', true )
					->first();

		$batch = new \stdClass();
		$batch->key = empty( $scan ) ? '0' : (string)$scan->id;
		$batch->data = empty( $scan ) ? [] : [ (int)$scan->id ];
		return $batch;
	}

	/**
	 * Dispatch
	 *
	 * @access public
	 * @return array|\WP_Error|false
	 */
	public function dispatch() {
		// ensure any scheduled scans have been saved before remote post
		$this->save();
		// Perform remote post.
		$result = parent::dispatch();
		if ( $result === false && !$this->is_queue_empty() ) {
			$this->schedule_event();
		}
		return $result;
	}

	protected function get_post_args() {
		$args = parent::get_post_args();

		/**
		 * Automatically add HTTP AUTH header if the current request has it.
		 */
		if ( Services::Request()->server[ 'HTTP_AUTHORIZATION' ] ?? false ) {
			if ( !\is_array( $args[ 'headers' ] ?? null ) ) {
				$args[ 'headers' ] = [];
			}
			$args[ 'headers' ][ 'Authorization' ] = Services::Request()->server[ 'HTTP_AUTHORIZATION' ];
		}

		return $args;
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param int $scanID
	 * @return mixed
	 */
	protected function task( $scanID ) {
		try {
			( new QueueInit() )->init( (int)$scanID );
		}
		catch ( \Throwable $e ) {
			( new RunState() )->markFailed( (int)$scanID, \sprintf( 'Scan queue build failed: %s', $e->getMessage() ) );
		}

		return false;
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
	}

	/**
	 * Delete queue
	 *
	 * @param string $scanID .
	 * @return $this
	 */
	public function delete( $scanID ) {
		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		return self::con()->db_con->scans->getQuerySelector()
				   ->filterByStatus( 'queued' )
				   ->filterByNotFinished()
				   ->count() < 1;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {
		return $this;
	}

	/**
	 * Update queue
	 *
	 * @param string $key  Key.
	 * @param mixed  $data Data.
	 * @return $this
	 */
	public function update( $key, $data ) {
		return $this;
	}
}
