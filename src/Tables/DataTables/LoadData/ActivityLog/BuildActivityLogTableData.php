<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImpossibleQueryException;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildActivityLogTableData extends BaseBuildTableData {

	/**
	 * @var LogRecord
	 */
	private $log;

	private bool $eventTextSearchComputed = false;

	private ?string $eventTextSearchWhere = null;

	protected function getSearchPanesDataBuilder() :BuildSearchPanesData {
		return new BuildSearchPanesData();
	}

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return $this->getSearchPanesDataBuilder()->build();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		$this->primeUserCache(
			\array_filter( \array_map( fn( $log ) => $log->meta_data[ 'uid' ] ?? '', $records ), '\is_numeric' )
		);

		return \array_values( \array_map(
			function ( $log ) {
				$this->log = $log;
				$data = $this->log->getRawData();
				$data[ 'ip' ] = $this->log->ip;
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'identity' ] = $this->getColumnContent_Identity();
				$data[ 'event' ] = self::con()->comps->events->getEventName( $this->log->event_slug );
				$this->log->created_at = \max( $this->log->updated_at, $this->log->created_at );
				$data[ 'created_since' ] = $this->getColumnContent_Date( $this->log->created_at );
				$data[ 'message' ] = $this->getColumnContent_Message();
				$data[ 'user' ] = $this->getColumnContent_User();
				$data[ 'uid' ] = $this->getColumnContent_UserID();
				$data[ 'level' ] = $this->getColumnContent_Level();
				$data[ 'severity' ] = $this->getColumnContent_SeverityIcon();
				$data[ 'meta' ] = $this->getColumnContent_Meta();
				$data[ 'day' ] = Services::Request()
										 ->carbon( true )->setTimestamp( $this->log->created_at )->toDateString();
				return $data;
			},
			$records
		) );
	}

	protected function validateSearchPanes( array $searchPanes ) :array {
		foreach ( $searchPanes as $column => &$values ) {
			switch ( $column ) {
				case 'event':
					$values = \array_filter( $values, fn( $event ) => !empty( $event ) && self::con()->comps->events->eventExists( $event ) );
					break;
				default:
					$values = $this->validateCommonColumn( $column, $values );
					break;
			}
		}
		return \array_filter( $searchPanes );
	}

	/**
	 * The `Where`s need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( \array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'day':
						$wheres[] = $this->buildSqlWhereForDaysSearch( $selected, 'log' );
						break;
					case 'event':
						if ( \count( $selected ) > 1 ) {
							$wheres[] = sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", $selected ) );
						}
						else {
							$wheres[] = sprintf( "`log`.`event_slug`='%s'", \array_pop( $selected ) );
						}
						break;
					case 'ip':
						$wheres[] = sprintf( "`ips`.`ip`=INET6_ATON('%s')", \array_pop( $selected ) );
						break;
					case 'user':
						if ( \count( $selected ) > 0 ) {
							$wheres[] = sprintf( "`req`.`uid` IN (%s)", \implode( ',', $selected ) );
						}
						break;
					default:
						break;
				}
			}
		}
		$wheres = \array_merge( $wheres, $this->buildWheresFromCommonSearchParams() );

		$eventSlugWhere = $this->buildSqlWhereForEventTextSearch();
		if ( !empty( $eventSlugWhere ) ) {
			$wheres[] = $eventSlugWhere;
		}

		return $wheres;
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader()->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		$loader = $this->getRecordsLoader();
		$loader->wheres = \array_merge( $loader->wheres ?? [], $this->buildWheresFromSearchParams() );
		return $loader->countAll();
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return \array_filter( \array_map(
			fn( $column ) => ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '',
			( new ForActivityLog() )->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return LogRecord[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader();
		$loader->wheres = \array_merge( $loader->wheres ?? [], $wheres );
		$loader->limit = $limit;
		$loader->offset = $offset;
		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->getOrderBy();
		return $loader->run();
	}

	protected function getValidEventSlugs() :array {
		return \array_keys( self::con()->comps->events->getEvents() );
	}

	protected function getRecordsLoader() :LoadLogs {
		$loader = new LoadLogs();
		$slugs = $this->getValidEventSlugs();
		if ( !empty( $slugs ) ) {
			$loader->wheres = [
				\sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", $slugs ) )
			];
		}
		return $loader;
	}

	private function buildSqlWhereForEventTextSearch() :string {
		if ( !$this->eventTextSearchComputed ) {
			$this->eventTextSearchComputed = true;

			$remaining = $this->parseSearchText()[ 'remaining' ];
			if ( !empty( $remaining ) ) {
				$matchingSlugs = ( new EventSlugSearch() )->findMatchingSlugs( $remaining );
				if ( empty( $matchingSlugs ) ) {
					throw new ImpossibleQueryException( 'No events match the search text.' );
				}
				$matchingSlugs = \array_intersect( $matchingSlugs, $this->getValidEventSlugs() );
				if ( empty( $matchingSlugs ) ) {
					throw new ImpossibleQueryException( 'No valid events match the search text.' );
				}
				$this->eventTextSearchWhere = \sprintf(
					"`log`.`event_slug` IN ('%s')",
					\implode( "','", $matchingSlugs )
				);
			}
		}

		return $this->eventTextSearchWhere ?? '';
	}

	private function getColumnContent_UserID() :string {
		return $this->log->meta_data[ 'uid' ] ?? '-';
	}

	protected function getColumnContent_Identity() :string {
		$ip = (string)$this->log->ip;
		if ( !empty( $ip ) ) {
			$ipID = $this->resolveIpIdentity( $ip );
			if ( $ipID !== null ) {
				if ( $ipID[ 0 ] === IpID::THIS_SERVER ) {
					$id = __( 'This Server', 'wp-simple-firewall' );
				}
				elseif ( $ipID[ 0 ] === IpID::VISITOR ) {
					$id = __( 'Your Current IP', 'wp-simple-firewall' );
				}
				elseif ( $ipID[ 0 ] === IpID::UNKNOWN ) {
					$id = __( 'Unidentified', 'wp-simple-firewall' );
				}
				else {
					$id = sprintf( '<code>%s</code>', $ipID[ 1 ] );
				}
			}
			else {
				$id = '';
			}

			$loggedIn = \is_numeric( $this->getColumnContent_UserID() );
			$content = \implode( '', \array_filter( [
				sprintf( '%s',
					$loggedIn ?
						sprintf( '%s and authenticated as %s', $id, $this->getColumnContent_User() )
						: sprintf( '%s and not authenticated', $id )
				),
				sprintf( '<h6 class="text-nowrap mb-0">%s</h6>',
					$this->getIpAnalysisLink( $ip )
				),
			] ) );
		}
		else {
			$content = 'No IP';
		}
		return $content;
	}

	protected function getColumnContent_User() :string {
		$content = '-';
		$uid = $this->log->meta_data[ 'uid' ] ?? '';
		if ( !empty( $uid ) ) {
			if ( \is_numeric( $uid ) ) {
				$content = $this->getUserHref( (int)$uid );
			}
			else {
				$content = $uid === 'cron' ? 'WP Cron' : 'WP-CLI';
			}
		}
		return $content;
	}

	private function getColumnContent_Message() :string {
		$msg = ActivityLogMessageBuilder::BuildFromLogRecord( $this->log, "<br/> \n" );
		return sprintf( '<span class="message-header">%s</span><p class="m-0">%s</p>',
			self::con()->comps->events->getEventName( $this->log->event_slug ),
			sanitize_textarea_field( \implode( "<br/>", $msg ) )
		);
	}

	private function getColumnContent_Meta() :string {
		$label = __( 'Click to display meta data for this request in a popover', 'wp-simple-firewall' );
		return sprintf( '<button type="button" aria-label="%s" class="btn btn-link" title="%s" data-toggle="popover" data-rid="%s">%s</button>',
			$label,
			$label,
			$this->log->rid,
			sprintf( '<span class="meta-icon">%s</span>', self::con()->svgs->raw( 'tags.svg' ) )
		);
	}

	private function getColumnContent_Level() :string {
		return self::con()->comps->events->getEventDef( $this->log->event_slug )[ 'level' ];
	}

	private function getColumnContent_SeverityIcon() :string {
		$level = $this->getColumnContent_Level();
		$levelDetails = [
							'alert'   => [
								'icon' => 'x-octagon',
							],
							'warning' => [
								'icon' => 'exclamation-triangle',
							],
							'notice'  => [
								'icon' => 'info-square',
							],
							'info'    => [
								'icon' => 'info-circle',
							],
							'debug'   => [
								'icon' => 'question-diamond',
							],
						][ $level ];
		return sprintf( '<div class="severity-%s severity-icon">%s</div>', $level,
			self::con()->svgs->raw( $levelDetails[ 'icon' ] )
		);
	}
}
