<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayText;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord as ActivityLogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LogRecord as RequestLogRecord,
	Ops\Handler as ReqLogsHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LiveLogRowsBuilder {

	use PluginControllerConsumer;

	/**
	 * This builder currently owns compact timestamp formatting plus activity and
	 * traffic row mapping. If its scope grows further, split it into smaller
	 * collaborators such as CompactTimestampFormatter, ActivityLogRowBuilder,
	 * and TrafficLogRowBuilder.
	 */

	/**
	 * @param ActivityLogRecord[] $records
	 */
	public function buildActivityRows( array $records ) :array {
		return \array_values( \array_map(
			fn( ActivityLogRecord $record ) => $this->buildActivityRow( $record ),
			$records
		) );
	}

	/**
	 * @param RequestLogRecord[] $records
	 */
	public function buildTrafficRows( array $records ) :array {
		return \array_values( \array_map(
			fn( RequestLogRecord $record ) => $this->buildTrafficRow( $record ),
			$records
		) );
	}

	public function buildCompactTimestamp( int $timestamp, ?int $referenceTimestamp = null ) :string {
		$referenceTimestamp = $referenceTimestamp ?? \time();
		$format = wp_date( 'Y-m-d', $timestamp ) === wp_date( 'Y-m-d', $referenceTimestamp )
			? 'H:i'
			: 'M j, H:i';

		return wp_date( $format, $timestamp );
	}

	public function buildActivityRow( ActivityLogRecord $record ) :array {
		$title = self::con()->comps->events->getEventName( $record->event_slug );
		$description = ActivityLogMessageBuilder::Build( $record->event_slug, $record->meta_data ?? [], ' ' );

		return [
			'timestamp'   => $this->buildCompactTimestamp( $record->created_at ),
			'ip'          => $record->ip,
			'ip_href'     => $this->buildIpHref( $record->ip ),
			'title'       => empty( $title ) ? $record->event_slug : $title,
			'description' => CommonDisplayText::truncate( $description, 140 ),
			'badges'      => [],
		];
	}

	public function buildTrafficRow( RequestLogRecord $record ) :array {
		return [
			'timestamp'   => $this->buildCompactTimestamp( $record->created_at ),
			'ip'          => $record->ip,
			'ip_href'     => $this->buildIpHref( $record->ip ),
			'title'       => $this->buildTrafficTitle( $record ),
			'description' => $this->buildTrafficDescription( $record ),
			'badges'      => $this->buildTrafficBadges( $record ),
		];
	}

	private function buildTrafficTitle( RequestLogRecord $record ) :string {
		$path = (string)$record->path;
		$query = \trim( (string)( $record->meta[ 'query' ] ?? '' ), '?' );
		if ( !empty( $query ) ) {
			$path .= '?'.$query;
		}

		return \sprintf(
			'%s %s',
			\strtoupper( empty( $record->verb ) ? 'GET' : $record->verb ),
			CommonDisplayText::truncate( $path === '' ? '/' : $path, 110 )
		);
	}

	private function buildTrafficDescription( RequestLogRecord $record ) :string {
		$parts = [];

		if ( $record->uid > 0 ) {
			$user = Services::WpUsers()->getUserById( $record->uid );
			$parts[] = \sprintf(
				/* translators: %s: WordPress user login or ID */
				__( 'User: %s', 'wp-simple-firewall' ),
				$user instanceof \WP_User ? $user->user_login : \sprintf( 'ID %d', $record->uid )
			);
		}

		if ( $record->code > 0 ) {
			$parts[] = \sprintf(
				/* translators: %s: HTTP response code */
				__( 'Response: %s', 'wp-simple-firewall' ),
				$record->code
			);
		}

		if ( $record->offense ) {
			$parts[] = __( 'Offense detected', 'wp-simple-firewall' );
		}

		return \implode( ' | ', $parts );
	}

	private function buildTrafficBadges( RequestLogRecord $record ) :array {
		$badges = [
			[
				'label' => ReqLogsHandler::GetTypeName( $record->type ),
				'class' => 'bg-secondary-subtle text-body-secondary border border-secondary-subtle',
			],
		];

		if ( $record->code > 0 ) {
			$badges[] = [
				'label' => (string)$record->code,
				'class' => $this->getResponseBadgeClass( $record->code ),
			];
		}

		if ( $record->offense ) {
			$badges[] = [
				'label' => __( 'Offense', 'wp-simple-firewall' ),
				'class' => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
			];
		}

		return $badges;
	}

	private function getResponseBadgeClass( int $code ) :string {
		if ( $code >= 400 ) {
			return 'bg-danger-subtle text-danger-emphasis border border-danger-subtle';
		}
		if ( $code >= 300 ) {
			return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
		}
		return 'bg-success-subtle text-success-emphasis border border-success-subtle';
	}

	private function buildIpHref( string $ip ) :string {
		return \filter_var( $ip, \FILTER_VALIDATE_IP ) ? self::con()->plugin_urls->ipAnalysis( $ip ) : '';
	}
}
