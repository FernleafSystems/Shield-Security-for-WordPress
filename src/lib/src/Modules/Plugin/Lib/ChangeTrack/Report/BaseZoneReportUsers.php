<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseZoneReportUsers extends BaseZoneReport {

	private $admins = [];

	protected function buildSummaryForLog( LogRecord $log ) :string {
		switch ( $log->event_slug ) {
			case 'user_password_updated':
				$text = __( 'Password Updated', 'wp-simple-firewall' );
				break;
			case 'user_registered':
				$text = sprintf( '%s/%s', __( 'Registered', 'wp-simple-firewall' ), __( 'Created', 'wp-simple-firewall' ) );
				break;
			case 'user_deleted':
				$text = __( 'Deleted', 'wp-simple-firewall' );
				break;
			default:
				$text = 'unknown event';
				break;
		}
		return $text;
	}

	protected function buildDetailsForLog( LogRecord $log ) :string {
		$WP = Services::WpGeneral();
		$user = Services::WpUsers()->getUserById( $log->meta_data[ 'uid' ] ?? 0 );
		$username = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) : $user->user_login;
		switch ( $log->event_slug ) {
			case 'user_password_updated':
				$text = empty( $user ) ?
					sprintf( '[%s] %s',
						$WP->getTimeStringForDisplay( $log->created_at, false ),
						sprintf( __( 'Password updated by %s from %s', 'wp-simple-firewall' ), $username, $log->ip )
					)
					:
					sprintf( '[%s] %s',
						$WP->getTimeStringForDisplay( $log->created_at, false ),
						sprintf( __( 'Password updated by %s from %s', 'wp-simple-firewall' ), $username, $log->ip )
					);
				break;
			case 'user_registered':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'User registered by %s from %s', 'wp-simple-firewall' ), $username, $log->ip )
				);
				break;
			case 'user_deleted':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'User deleted by %s from %s', 'wp-simple-firewall' ), $username, $log->ip )
				);
				break;
			default:
				$text = 'unknown event';
				break;
		}
		return $text;
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", implode( "','", [
				'user_password_updated',
				'user_registered',
				'user_deleted',
			] ) ),
		];
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return $log->meta_data[ 'user_login' ];
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		$WPU = Services::WpUsers();
		$user = $WPU->getUserByUsername( $log->meta_data[ 'user_login' ] );
		if ( empty( $user ) ) {
			$link = [
				'href' => Services::WpGeneral()->getAdminUrl( 'users.php' ),
				'text' => __( 'Users' ),
			];
		}
		else {
			$link = [
				'href' => $WPU->getAdminUrl_ProfileEdit( $user ),
				'text' => __( 'Profile' ),
			];
		}
		return $link;
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->meta_data[ 'user_login' ];
	}

	protected function isUserAdmin( string $userLogin ) {
		if ( !isset( $this->admins[ $userLogin ] ) ) {
			$this->admins[ $userLogin ] = user_can(
				Services::WpUsers()->getUserByUsername( $userLogin ),
				'manage_options'
			);
		}
		return $this->admins[ $userLogin ];
	}
}