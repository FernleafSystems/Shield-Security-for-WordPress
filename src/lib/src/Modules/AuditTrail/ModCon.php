<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'audit_trail';

	/**
	 * @var Lib\AuditLogger
	 */
	private $auditLogger;

	public function getDbH_Logs() :DB\Logs\Ops\Handler {
		$this->getCon()->getModule_Data()->getDbH_ReqLogs();
		return $this->getDbHandler()->loadDbH( 'at_logs' );
	}

	public function getDbH_Meta() :DB\Meta\Ops\Handler {
		$this->getDbH_Logs();
		return $this->getDbHandler()->loadDbH( 'at_meta' );
	}

	/**
	 * @deprecated 12.1
	 */
	public function getDbHandler_AuditTrail() :Shield\Databases\AuditTrail\Handler {
		return $this->getDbH( 'audit_trail' );
	}

	public function getAuditLogger() :Lib\AuditLogger {
		return $this->auditLogger ?? $this->auditLogger = new Lib\AuditLogger( $this->getCon() );
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbH_Logs()->isReady();
	}

	/**
	 * TODO: This requires some fairly convoluted SQL to pick out records for a specific user in an efficient manner
	 * @param array  $exportItems
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function onWpPrivacyExport( $exportItems, $email, $page = 1 ) :array {

		$user = Services::WpUsers()->getUserByEmail( $email );
		if ( !empty( $user ) ) {
			$con = $this->getCon();
			$WP = Services::WpGeneral();
			$exportData = array_map(
				function ( $log ) use ( $WP ) {
					return [
						'name'  => sprintf( '%s', $WP->getTimeStringForDisplay( $log[ 'created_at' ] ) ),
						'value' => sprintf( '[IP:%s] %s', $log[ 'ip' ], $log[ 'message' ] )
					];
				},
				array_filter( // Get all logs entries pertaining to this user:
					( new Shield\Modules\AuditTrail\Lib\LogTable\BuildAuditTableData() )
						->setMod( $this )
						->loadForRecords(),
					function ( $log ) use ( $user ) {
						$keep = $log[ 'user_id' ] === $user->ID;
						if ( !$keep ) {
							$userParts = array_map( 'preg_quote', [ $user->user_login, $user->user_email ] );
							$keep = preg_match( sprintf( '/(%s)/i', implode( '|', $userParts ) ), $log[ 'message' ] ) > 0;
						}
						return $keep;
					}
				)
			);

			if ( !empty( $exportData ) ) {
				$exportItems[] = [
					'group_id'          => $this->getModSlug(),
					'group_label'       => sprintf( __( '[%s] Activity Log Entries', 'wp-simple-firewall' ),
						$con->getHumanName() ),
					'group_description' => sprintf( __( '[%s] Activity Log Entries referencing the given user.', 'wp-simple-firewall' ),
						$con->getHumanName() ),
					'item_id'           => $con->prefix( 'audit-trail' ),
					'data'              => $exportData,
				];
			}
		}

		return is_array( $exportItems ) ? $exportItems : [];
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 *
	 * @param array  $data
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function onWpPrivacyErase( $data, $email, $page = 1 ) {
		try {
			$user = Services::WpUsers()->getUserByEmail( $email );
			if ( !empty( $user ) ) {
				$deleter = $this->getDbH_Meta()->getQueryDeleter();
				$deleter->addWhereEquals( 'meta_key', 'uid' )
						->addWhereEquals( 'meta_data', $user->ID )
						->query();
				$deleter->addWhereEquals( 'meta_key', 'user_login' )
						->addWhereEquals( 'meta_data', $user->user_login )
						->query();
				$deleter->addWhereEquals( 'meta_key', 'email' )
						->addWhereEquals( 'meta_data', $user->user_email )
						->query();
				$data[ 'messages' ][] = sprintf( '%s Audit Entries deleted', $this->getCon()->getHumanName() );
			}
		}
		catch ( \Exception $e ) {
		}
		return $data;
	}
}