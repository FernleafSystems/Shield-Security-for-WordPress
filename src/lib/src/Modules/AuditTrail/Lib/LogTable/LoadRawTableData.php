<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadRawTableData {

	use ModConsumer;

	/**
	 * @var LogRecord
	 */
	private $log;

	public function loadForLogs() :array {
		return array_values( array_map(
			function ( $log ) {
				$this->log = $log;

				$data = $log->getRawData();
				$data[ 'ip' ] = $log->ip;
				$data[ 'created_since' ] = Services::Request()
												   ->carbon( true )
												   ->setTimestamp( $log->created_at )
												   ->diffForHumans();
				$data[ 'message' ] = $this->getColumnContent_Message();
				$data[ 'user' ] = $this->getColumnContent_User();
				$data[ 'level' ] = $this->getColumnContent_Level();
				return $data;
			},
			( new LoadLogs() )
				->setMod( $this->getCon()->getModule_AuditTrail() )
				->run()
		) );
	}

	private function getColumnContent_Message() :string {
		return AuditMessageBuilder::Build( $this->log->event_slug, $this->log->meta_data );
	}

	private function getColumnContent_User() :string {
		$content = '-';
		$uid = $this->log->meta_data[ 'uid' ] ?? '';
		if ( !empty( $uid ) ) {
			if ( is_numeric( $uid ) ) {
				$user = Services::WpUsers()->getUserById( $uid );
				if ( $user instanceof \WP_User ) {
					$content = sprintf( '%s', $user->user_login );
				}
				else {
					$content = sprintf( 'User Unavailable (%s)', $uid );
				}
			}
			else {
				$content = $uid === 'cron' ? 'WP Cron' : 'WP-CLI';
			}
		}
		return $content;
	}

	private function getColumnContent_Level() :string {
		return 'Warning';
	}
}