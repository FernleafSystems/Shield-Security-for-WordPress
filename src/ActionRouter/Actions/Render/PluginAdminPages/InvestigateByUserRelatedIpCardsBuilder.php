<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByUserRelatedIpCardsBuilder {

	use PluginControllerConsumer;

	public function build( array $sessions, array $activityLogs, array $requestLogs ) :array {
		$byIp = [];

		foreach ( $sessions as $session ) {
			$ip = (string)( $session[ 'ip' ] ?? '' );
			if ( $ip === '' ) {
				continue;
			}
			$timestamp = (int)( $session[ 'last_activity_ts' ] ?? 0 );
			$byIp[ $ip ] = $byIp[ $ip ] ?? $this->newIpCardSeed( $ip );
			$byIp[ $ip ][ 'sessions_count' ]++;
			$byIp[ $ip ][ 'last_seen_ts' ] = \max( $byIp[ $ip ][ 'last_seen_ts' ], $timestamp );
		}

		foreach ( $activityLogs as $log ) {
			$ip = (string)( $log[ 'ip' ] ?? '' );
			if ( $ip === '' ) {
				continue;
			}
			$timestamp = (int)( $log[ 'created_at_ts' ] ?? 0 );
			$byIp[ $ip ] = $byIp[ $ip ] ?? $this->newIpCardSeed( $ip );
			$byIp[ $ip ][ 'activity_count' ]++;
			$byIp[ $ip ][ 'last_seen_ts' ] = \max( $byIp[ $ip ][ 'last_seen_ts' ], $timestamp );
		}

		foreach ( $requestLogs as $log ) {
			$ip = (string)( $log[ 'ip' ] ?? '' );
			if ( $ip === '' ) {
				continue;
			}
			$timestamp = (int)( $log[ 'created_at_ts' ] ?? 0 );
			$byIp[ $ip ] = $byIp[ $ip ] ?? $this->newIpCardSeed( $ip );
			$byIp[ $ip ][ 'requests_count' ]++;
			$byIp[ $ip ][ 'has_offense' ] = $byIp[ $ip ][ 'has_offense' ] || !empty( $log[ 'offense' ] );
			$byIp[ $ip ][ 'last_seen_ts' ] = \max( $byIp[ $ip ][ 'last_seen_ts' ], $timestamp );
		}

		foreach ( $byIp as &$card ) {
			$statuses = [];
			if ( $card[ 'sessions_count' ] > 0 ) {
				$statuses[] = 'good';
			}
			if ( $card[ 'requests_count' ] > 0 ) {
				$statuses[] = 'warning';
			}
			if ( $card[ 'has_offense' ] ) {
				$statuses[] = 'critical';
			}
			$card[ 'status' ] = StatusPriority::highest( $statuses );
			$card[ 'status_label' ] = $this->mapStatusLabel( $card[ 'status' ] );

			if ( $card[ 'last_seen_ts' ] > 0 ) {
				$card[ 'last_seen_at' ] = Services::WpGeneral()->getTimeStringForDisplay( $card[ 'last_seen_ts' ] );
				$card[ 'last_seen_ago' ] = $this->getTimeAgo( $card[ 'last_seen_ts' ] );
			}
			unset( $card[ 'has_offense' ] );
		}
		unset( $card );

		\uasort( $byIp, static fn( array $a, array $b ) :int => $b[ 'last_seen_ts' ] <=> $a[ 'last_seen_ts' ] );

		return \array_values( $byIp );
	}

	private function newIpCardSeed( string $ip ) :array {
		return [
			'ip'               => $ip,
			'href'             => self::con()->plugin_urls->ipAnalysis( $ip ),
			'investigate_href' => self::con()->plugin_urls->investigateByIp( $ip ),
			'last_seen_ts'     => 0,
			'last_seen_at'     => '',
			'last_seen_ago'    => '',
			'sessions_count'   => 0,
			'activity_count'   => 0,
			'requests_count'   => 0,
			'status'           => 'info',
			'status_label'     => $this->mapStatusLabel( 'info' ),
			'has_offense'      => false,
		];
	}

	private function mapStatusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Offense Detected', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Requests Observed', 'wp-simple-firewall' );
			case 'good':
				return __( 'Sessions Observed', 'wp-simple-firewall' );
			default:
				return __( 'No Recent Signals', 'wp-simple-firewall' );
		}
	}

	private function getTimeAgo( int $timestamp ) :string {
		return $timestamp <= 0
			? ''
			: Services::Request()
					  ->carbon( true )
					  ->setTimestamp( $timestamp )
					  ->diffForHumans();
	}
}
