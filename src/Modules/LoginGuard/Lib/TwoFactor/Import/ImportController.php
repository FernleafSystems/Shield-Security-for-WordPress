<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Import;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const OPT_RUN_STATE = 'mfa_import_run';
	public const STATUS_IDLE = 'idle';
	public const STATUS_QUEUED = 'queued';
	public const STATUS_RUNNING = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_FAILED = 'failed';

	private ?ImportQueue $queue = null;

	protected function run() {
		$this->getQueue();
	}

	public function startImportRun( string $supplierSlug ) :array {
		$supplierSlug = \sanitize_key( $supplierSlug );
		$bridge = $this->getSupplierBridge( $supplierSlug );

		if ( $bridge === null ) {
			throw new \InvalidArgumentException( 'Unrecognised MFA import supplier.' );
		}

		if ( $this->hasActiveRun() ) {
			throw new \RuntimeException( 'An MFA import is already queued or running.' );
		}

		if ( $bridge instanceof WordfenceLoginSecurityBridge && !$bridge->hasSecretsTable() ) {
			return $this->markRunFailed( $supplierSlug, 'Wordfence Login Security source table is missing.' );
		}

		$this->getQueue()->cleanupTransportState();

		$usersTotal = $this->countUsers();
		$pagesTotal = $usersTotal > 0 ? (int)\ceil( $usersTotal/ProcessUserPage::PAGE_SIZE ) : 0;

		$state = $this->normalizeRunState( [
			'supplier_slug'    => $supplierSlug,
			'status'           => $pagesTotal > 0 ? self::STATUS_QUEUED : self::STATUS_COMPLETED,
			'started_at'       => Services::Request()->ts(),
			'finished_at'      => $pagesTotal > 0 ? 0 : Services::Request()->ts(),
			'page_size'        => ProcessUserPage::PAGE_SIZE,
			'pages_total'      => $pagesTotal,
			'pages_processed'  => 0,
			'users_total'      => $usersTotal,
			'users_processed'  => 0,
			'users_with_source_state' => 0,
			'users_with_imports' => 0,
			'user_errors'      => 0,
			'imported_factors' => [],
			'skipped_factors'  => [],
			'last_error'       => '',
		] );

		$this->storeRunState( $state );

		if ( $pagesTotal > 0 ) {
			$queue = $this->getQueue();
			for ( $page = 1; $page <= $pagesTotal; $page++ ) {
				$queue->push_to_queue( $page );
			}
			$queue->save()->dispatch();
		}

		return $this->getRunState();
	}

	/**
	 * @return array{
	 *   supplier_slug:string,
	 *   status:string,
	 *   started_at:int,
	 *   finished_at:int,
	 *   page_size:int,
	 *   pages_total:int,
	 *   pages_processed:int,
	 *   users_total:int,
	 *   users_processed:int,
	 *   users_with_source_state:int,
	 *   users_with_imports:int,
	 *   user_errors:int,
	 *   imported_factors:array<string, int>,
	 *   skipped_factors:array<string, array<string, int>>,
	 *   last_error:string
	 * }
	 */
	public function getRunState() :array {
		return $this->normalizeRunState(
			\is_array( self::con()->opts->optGet( self::OPT_RUN_STATE ) )
				? self::con()->opts->optGet( self::OPT_RUN_STATE )
				: []
		);
	}

	public function processPage( int $page ) :void {
		$state = $this->getRunState();
		$bridge = $this->getSupplierBridge( $state[ 'supplier_slug' ] );

		if ( $bridge === null ) {
			$this->markRunFailed( $state[ 'supplier_slug' ], 'The queued MFA import supplier is no longer available.' );
			return;
		}

		if ( $state[ 'status' ] === self::STATUS_QUEUED ) {
			$state[ 'status' ] = self::STATUS_RUNNING;
		}

		$summary = ( new ProcessUserPage() )->run( $bridge, $page );

		$state[ 'users_processed' ] += $summary[ 'users_processed' ];
		$state[ 'users_with_source_state' ] += $summary[ 'users_with_source_state' ];
		$state[ 'users_with_imports' ] += $summary[ 'users_with_imports' ];
		$state[ 'user_errors' ] += $summary[ 'user_errors' ];
		$state[ 'pages_processed' ]++;

		foreach ( $summary[ 'imported_factors' ] as $factorSlug => $count ) {
			$state[ 'imported_factors' ][ $factorSlug ] = (int)( $state[ 'imported_factors' ][ $factorSlug ] ?? 0 ) + (int)$count;
		}

		foreach ( $summary[ 'skipped_factors' ] as $factorSlug => $reasons ) {
			foreach ( $reasons as $reason => $count ) {
				$state[ 'skipped_factors' ][ $factorSlug ][ $reason ]
					= (int)( $state[ 'skipped_factors' ][ $factorSlug ][ $reason ] ?? 0 ) + (int)$count;
			}
		}

		if ( $summary[ 'last_error' ] !== '' ) {
			$state[ 'last_error' ] = $summary[ 'last_error' ];
		}

		$this->storeRunState( $state );
	}

	public function markRunCompleted() :array {
		$state = $this->getRunState();
		if ( \in_array( $state[ 'status' ], [ self::STATUS_QUEUED, self::STATUS_RUNNING ], true ) ) {
			$state[ 'status' ] = self::STATUS_COMPLETED;
		}
		$state[ 'finished_at' ] = Services::Request()->ts();
		$this->storeRunState( $state );
		$this->getQueue()->cleanupTransportState();

		return $state;
	}

	public function markRunFailed( string $supplierSlug, string $message ) :array {
		$state = $this->normalizeRunState( [
			'supplier_slug' => \sanitize_key( $supplierSlug ),
			'status'        => self::STATUS_FAILED,
			'started_at'    => Services::Request()->ts(),
			'finished_at'   => Services::Request()->ts(),
			'last_error'    => $message,
		] );
		$this->storeRunState( $state );
		$this->getQueue()->cleanupTransportState();

		return $state;
	}

	private function hasActiveRun() :bool {
		return \in_array( $this->getRunState()[ 'status' ], [ self::STATUS_QUEUED, self::STATUS_RUNNING ], true )
			   || $this->getQueue()->is_active();
	}

	private function storeRunState( array $state ) :void {
		self::con()->opts->optSet( self::OPT_RUN_STATE, $this->normalizeRunState( $state ) )->store();
	}

	/**
	 * @return array<string, SupplierBridgeInterface>
	 */
	protected function buildSupplierBridges() :array {
		$bridges = [
			new WordpressTwoFactorBridge(),
			new WordfenceLoginSecurityBridge(),
			new SolidSecurityBridge(),
		];

		return \array_combine(
			\array_map( static fn( SupplierBridgeInterface $bridge ) => $bridge->getSupplierSlug(), $bridges ),
			$bridges
		) ?: [];
	}

	private function getSupplierBridge( string $supplierSlug ) :?SupplierBridgeInterface {
		$bridges = $this->buildSupplierBridges();
		return $bridges[ $supplierSlug ] ?? null;
	}

	private function getQueue() :ImportQueue {
		return $this->queue ??= new ImportQueue();
	}

	private function countUsers() :int {
		$query = new \WP_User_Query( [
			'number'        => 1,
			'paged'         => 1,
			'fields'        => 'ids',
			'orderby'       => 'ID',
			'order'         => 'ASC',
			'count_total'   => true,
			'cache_results' => false,
		] );

		return \max( 0, (int)$query->get_total() );
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array{
	 *   supplier_slug:string,
	 *   status:string,
	 *   started_at:int,
	 *   finished_at:int,
	 *   page_size:int,
	 *   pages_total:int,
	 *   pages_processed:int,
	 *   users_total:int,
	 *   users_processed:int,
	 *   users_with_source_state:int,
	 *   users_with_imports:int,
	 *   user_errors:int,
	 *   imported_factors:array<string, int>,
	 *   skipped_factors:array<string, array<string, int>>,
	 *   last_error:string
	 * }
	 */
	private function normalizeRunState( array $state ) :array {
		$normalized = [
			'supplier_slug' => '',
			'status' => self::STATUS_IDLE,
			'started_at' => 0,
			'finished_at' => 0,
			'page_size' => ProcessUserPage::PAGE_SIZE,
			'pages_total' => 0,
			'pages_processed' => 0,
			'users_total' => 0,
			'users_processed' => 0,
			'users_with_source_state' => 0,
			'users_with_imports' => 0,
			'user_errors' => 0,
			'imported_factors' => [],
			'skipped_factors' => [],
			'last_error' => '',
		];

		$normalized[ 'supplier_slug' ] = \sanitize_key( (string)( $state[ 'supplier_slug' ] ?? '' ) );
		$normalized[ 'status' ] = \in_array(
			(string)( $state[ 'status' ] ?? '' ),
			[ self::STATUS_IDLE, self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED ],
			true
		) ? (string)$state[ 'status' ] : self::STATUS_IDLE;

		foreach ( [
			'started_at',
			'finished_at',
			'page_size',
			'pages_total',
			'pages_processed',
			'users_total',
			'users_processed',
			'users_with_source_state',
			'users_with_imports',
			'user_errors',
		] as $key ) {
			$normalized[ $key ] = \max( 0, (int)( $state[ $key ] ?? $normalized[ $key ] ) );
		}

		$normalized[ 'imported_factors' ] = \array_reduce(
			\array_keys( \is_array( $state[ 'imported_factors' ] ?? null ) ? $state[ 'imported_factors' ] : [] ),
			function ( array $carry, string $factorSlug ) use ( $state ) {
				$carry[ $factorSlug ] = \max( 0, (int)( $state[ 'imported_factors' ][ $factorSlug ] ?? 0 ) );
				return $carry;
			},
			[]
		);

		$normalized[ 'skipped_factors' ] = [];
		foreach ( \is_array( $state[ 'skipped_factors' ] ?? null ) ? $state[ 'skipped_factors' ] : [] as $factorSlug => $reasons ) {
			if ( !\is_array( $reasons ) ) {
				continue;
			}
			foreach ( $reasons as $reason => $count ) {
				$normalized[ 'skipped_factors' ][ (string)$factorSlug ][ (string)$reason ] = \max( 0, (int)$count );
			}
		}

		$normalized[ 'last_error' ] = (string)( $state[ 'last_error' ] ?? '' );

		return $normalized;
	}
}
