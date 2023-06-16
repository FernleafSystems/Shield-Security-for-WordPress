<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

abstract class BaseZoneReport {

	use ModConsumer;

	protected $from;

	protected $until;

	protected $logs = null;

	protected $isSummary = true;

	public function __construct( int $from, int $until ) {
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
		if ( !\is_array( $this->logs ) ) {
			$this->logs = $this->loadLogs();
		}
		return $this->changesFromLogs();
	}

	/**
	 * @return LogRecord[]
	 */
	protected function loadLogs() :array {
		$loader = new LoadLogs();
		$loader->wheres = array_merge( $this->getLoadLogsWheres(), [
			sprintf( "`log`.`created_at`>%s", $this->from ),
			sprintf( "`log`.`created_at`<%s", $this->until ),
		] );
		return $loader->run();
	}

	abstract protected function getLoadLogsWheres() :array;

	protected function changesFromLogs() :array {
		$changes = [];
		foreach ( \is_array( $this->logs ) ? $this->logs : [] as $log ) {
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
			foreach ( $changes as $uniq => &$itemChanges ) {
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

	protected function buildSummaryForLog( LogRecord $log ) :string {
		return \implode( '<br/>', ActivityLogMessageBuilder::BuildFromLogRecord( $log ) );
	}

	protected function buildDetailsForLog( LogRecord $log ) :string {
		return \implode( '<br/>', ActivityLogMessageBuilder::BuildFromLogRecord( $log ) );
	}

	abstract protected function getUniqFromLog( LogRecord $log ) :string;

	protected function getNameForLog( LogRecord $log ) :string {
		return 'Unknown Item';
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [
			'href' => '#',
			'text' => 'Unknown Href',
		];
	}

	abstract public function getZoneName() :string;

	public static function Slug() :string {
		return ( new \ReflectionClass( static::class ) )->getShortName();
	}

	public function setFrom( int $form ) {
		$this->from = $form;
	}

	public function setUntil( int $until ) {
		$this->until = $until;
	}
}