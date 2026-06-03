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

	protected int $from;

	protected int $until;

	protected ?array $logs = null;

	protected bool $isSummary = true;

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

	/**
	 * @return array<string,array{
	 *     uniq:string,
	 *     rows:list<array{lines:list<string>,count:int,detail_time?:string,detail_who?:string}>,
	 *     link:array{href:string,text:string}|array{},
	 *     name:string
	 * }>
	 */
	protected function changesFromLogs() :array {
		$changes = [];
		foreach ( $this->loadLogs() as $log ) {
			$uniq = $this->getUniqFromLog( $log );
			if ( !isset( $changes[ $uniq ] ) ) {
				$changes[ $uniq ] = [
					'uniq' => $uniq,
					'rows' => [],
					'link' => $this->normaliseLinkForLog( $log ),
					'name' => (string)$this->getNameForLog( $log ),
				];
			}

			$changes[ $uniq ][ 'rows' ][] = $this->buildReportRowForLog( $log );
		}

		if ( $this->isSummary ) {
			foreach ( $changes as &$itemChanges ) {
				$uniqueChanges = [];
				foreach ( $itemChanges[ 'rows' ] as $row ) {
					$rowKey = \implode( "\n", $row[ 'lines' ] );
					if ( !isset( $uniqueChanges[ $rowKey ] ) ) {
						$uniqueChanges[ $rowKey ] = [
							'lines' => $row[ 'lines' ],
							'count' => 0,
						];
					}
					$uniqueChanges[ $rowKey ][ 'count' ]++;
				}

				$itemChanges[ 'rows' ] = \array_values( $uniqueChanges );
			}
		}

		return $changes;
	}

	public function countChanges() :int {
		return \count( $this->loadLogs() );
	}

	/**
	 * @return list<string>
	 */
	protected function buildSummaryLinesForLog( LogRecord $log ) :array {
		return \array_map(
			static fn( $line ) :string => (string)$line,
			ActivityLogMessageBuilder::BuildFromLogRecord( $log )
		);
	}

	/**
	 * @return array{lines:list<string>,count:int,detail_time?:string,detail_who?:string}
	 */
	protected function buildReportRowForLog( LogRecord $log ) :array {
		$row = [
			'lines' => $this->buildSummaryLinesForLog( $log ),
			'count' => 1,
		];

		if ( !$this->isSummary ) {
			$row[ 'detail_time' ] = Services::WpGeneral()->getTimeStringForDisplay( $log->created_at, false );
			$row[ 'detail_who' ] = $this->buildDetailWhoForLog( $log );
		}

		return $row;
	}

	protected function buildDetailWhoForLog( LogRecord $log ) :string {
		if ( $log->meta_data[ 'snapshot_discovery' ] ?? false ) {
			$who = __( 'Discovered', 'wp-simple-firewall' );
		}
		else {
			$user = Services::WpUsers()->getUserById( $log->meta_data[ 'uid' ] ?? 0 );
			$username = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) : $user->user_login;
			/* translators: %1$s: IP address, %2$s: username */
			$who = sprintf( __( '[%1$s] [%2$s]', 'wp-simple-firewall' ), $log->ip, \strtolower( $username ) );
		}
		return $who;
	}

	abstract protected function getUniqFromLog( LogRecord $log ) :string;

	protected function getNameForLog( LogRecord $log ) :string {
		return __( 'Unknown item', 'wp-simple-firewall' );
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [];
	}

	/**
	 * @return array{href:string,text:string}|array{}
	 */
	protected function normaliseLinkForLog( LogRecord $log ) :array {
		$link = $this->getLinkForLog( $log );
		$href = esc_url_raw( (string)( $link[ 'href' ] ?? '' ) );
		$text = (string)( $link[ 'text' ] ?? '' );

		return empty( $href ) || empty( $text ) ? [] : [
			'href' => $href,
			'text' => $text,
		];
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
