<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayText;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord as ActivityLogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LogRecord as RequestLogRecord,
	Ops\Handler as ReqLogsHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\ResolvesIpIdentity;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @phpstan-type LiveLogBadge array{
 *   label:string,
 *   class:string
 * }
 * @phpstan-type LiveLogRow array{
 *   timestamp:string,
 *   ip:string,
 *   ip_href:string,
 *   title:string,
 *   description:string,
 *   badges:list<LiveLogBadge>
 * }
 */
class LiveLogRowsBuilder {

	use PluginControllerConsumer;
	use ResolvesIpIdentity;

	/**
	 * This builder currently owns compact timestamp formatting plus activity and
	 * traffic row mapping. If its scope grows further, split it into smaller
	 * collaborators such as CompactTimestampFormatter, ActivityLogRowBuilder,
	 * and TrafficLogRowBuilder.
	 */

	/**
	 * @param ActivityLogRecord[] $records
	 * @return list<LiveLogRow>
	 */
	public function buildActivityRows( array $records ) :array {
		return \array_values( \array_map(
			fn( ActivityLogRecord $record ) => $this->buildActivityRow( $record ),
			$records
		) );
	}

	/**
	 * @param RequestLogRecord[] $records
	 * @return list<LiveLogRow>
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
			? 'H:i:s'
			: 'M j, H:i:s';

		return wp_date( $format, $timestamp );
	}

	/**
	 * @return LiveLogRow
	 */
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

	/**
	 * @return LiveLogRow
	 */
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

		if ( $record->offense ) {
			$parts[] = __( 'Offense detected', 'wp-simple-firewall' );
		}

		return \implode( ' | ', $parts );
	}

	/**
	 * @return list<LiveLogBadge>
	 */
	private function buildTrafficBadges( RequestLogRecord $record ) :array {
		$badges = [
			[
				'label' => ReqLogsHandler::GetTypeName( $record->type ),
				'class' => 'bg-secondary-subtle text-body-secondary border border-secondary-subtle',
			],
		];

		$identityBadge = $this->buildTrafficIdentityBadge( $record );
		if ( $identityBadge !== null ) {
			$badges[] = $identityBadge;
		}

		if ( $record->uid > 0 ) {
			$badges[] = [
				'label' => $this->buildTrafficUserLabel( $record ),
				'class' => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
			];
		}

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

	/**
	 * @return LiveLogBadge|null
	 */
	private function buildTrafficIdentityBadge( RequestLogRecord $record ) :?array {
		$identity = $this->resolveIpIdentity( $record->ip, $record->meta[ 'ua' ] ?? null );
		if ( $identity === null || $identity[ 0 ] === IpID::UNKNOWN ) {
			return null;
		}

		return [
			'label' => $identity[ 1 ],
			'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
		];
	}

	private function buildTrafficUserLabel( RequestLogRecord $record ) :string {
		$user = Services::WpUsers()->getUserById( $record->uid );

		return \is_object( $user ) && isset( $user->user_login ) && \is_string( $user->user_login ) && $user->user_login !== ''
			? $user->user_login
			: \sprintf( 'ID %d', $record->uid );
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
