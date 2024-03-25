<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
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
				$text = parent::buildSummaryForLog( $log );
				break;
		}
		return $text;
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", [
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