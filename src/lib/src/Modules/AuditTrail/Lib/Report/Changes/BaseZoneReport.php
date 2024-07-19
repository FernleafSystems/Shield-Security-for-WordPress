<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseZoneReport {

	use PluginControllerConsumer;

	protected $from;

	protected $until;

	protected $logs = null;

	protected $isSummary = true;

	public function __construct( int $from = 0, int $until = \PHP_INT_MAX ) {
		$this->from = $from;
		$this->until = $until;
	}

	public function getZoneDescription() :array {
		return [
			'TODO: Zone Description'
		];
	}

	public function buildChangeReportData( bool $isSummary ) :array {
		$this->isSummary = $isSummary;
		return $this->changesFromLogs();
	}

	/**
	 * @return LogRecord[]
	 */
	protected function loadLogs() :array {
		if ( !\is_array( $this->logs ) ) {
			$loader = new LoadLogs();
			$loader->wheres = \array_merge( $this->getLoadLogsWheres(), [
				sprintf( "`log`.`created_at`>%s", $this->from ),
				sprintf( "`log`.`created_at`<%s", $this->until ),
			] );
			$this->logs = $loader->run();
		}
		return $this->logs;
	}

	abstract protected function getLoadLogsWheres() :array;

	protected function changesFromLogs() :array {
		$changes = [];
		foreach ( $this->loadLogs() as $log ) {
			$uniq = $this->getUniqFromLog( $log );
			if ( !isset( $changes[ $uniq ] ) ) {
				$changes[ $uniq ] = [
					'uniq' => $uniq,
					'rows' => [],
					'link' => $this->getLinkForLog( $log ),
					'name' => $this->getNameForLog( $log ),
				];
			}

			$changes[ $uniq ][ 'rows' ][] = $this->isSummary ? $this->buildSummaryForLog( $log ) : $this->buildDetailsForLog( $log );
		}

		if ( $this->isSummary ) {
			foreach ( $changes as &$itemChanges ) {
				$uniqueChanges = [];
				foreach ( $itemChanges[ 'rows' ] as $row ) {
					if ( !isset( $uniqueChanges[ $row ] ) ) {
						$uniqueChanges[ $row ] = 0;
					}
					$uniqueChanges[ $row ]++;
				}

				$itemChanges[ 'rows' ] = [];
				foreach ( $uniqueChanges as $uniqueChange => $count ) {
					$itemChanges[ 'rows' ][] = $count > 1 ? sprintf( '%s (x%s)', $uniqueChange, $count ) : $uniqueChange;
				}
			}
		}

		return $changes;
	}

	public function countChanges() :int {
		return \count( $this->loadLogs() );
	}

	protected function buildSummaryForLog( LogRecord $log ) :string {
		return \implode( '<br/>', ActivityLogMessageBuilder::BuildFromLogRecord( $log ) );
	}

	protected function buildDetailsForLog( LogRecord $log ) :string {
		return $this->buildDetailedRow( $log, $this->buildSummaryForLog( $log ) );
	}

	protected function buildDetailedRow( LogRecord $log, string $rowBody ) :string {
		if ( $log->meta_data[ 'snapshot_discovery' ] ?? false ) {
			$who = sprintf( '<span class="badge text-bg-warning">%s</span>', __( 'Discovered', 'wp-simple-firewall' ) );
		}
		else {
			$user = Services::WpUsers()->getUserById( $log->meta_data[ 'uid' ] ?? 0 );
			$username = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) : $user->user_login;
			$who = sprintf( '[%s] [%s]', $log->ip, \strtolower( $username ) );
		}
		return sprintf( '%s<div class="detailed d-none"><small class="">[%s] %s</small></div>',
			$rowBody,
			Services::WpGeneral()->getTimeStringForDisplay( $log->created_at, false ),
			$who
		);
	}

	abstract protected function getUniqFromLog( LogRecord $log ) :string;

	protected function getNameForLog( LogRecord $log ) :string {
		return 'Unknown Item';
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [];
	}

	abstract public function getZoneName() :string;

	public static function Slug() :string {
		return \str_replace( 'zonereport', '', \strtolower( ( new \ReflectionClass( static::class ) )->getShortName() ) );
	}

	public function setFrom( int $form ) {
		$this->from = $form;
	}

	public function setUntil( int $until ) {
		$this->until = $until;
	}
}