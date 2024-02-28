<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'audit_trail';

	/**
	 * @var Lib\AuditCon
	 * @deprecated 19.1
	 */
	private $auditCon;

	/**
	 * @deprecated 19.1
	 */
	public function getAuditCon() :Lib\AuditCon {
		return isset( self::con()->comps ) ? self::con()->comps->activity_log :
			( $this->auditCon ?? $this->auditCon = new Lib\AuditCon() );
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
			$con = self::con();
			$WP = Services::WpGeneral();
			$exportData = \array_map(
				function ( $log ) use ( $WP ) {
					return [
						'name'  => sprintf( '%s', $WP->getTimeStringForDisplay( $log[ 'created_at' ] ) ),
						'value' => sprintf( '[IP:%s] %s', $log[ 'ip' ], $log[ 'message' ] )
					];
				},
				\array_filter( // Get all logs entries pertaining to this user:
					( new BuildActivityLogTableData() )->loadForRecords(),
					function ( $log ) use ( $user ) {
						$keep = $log[ 'user_id' ] === $user->ID;
						if ( !$keep ) {
							$userParts = \array_map( 'preg_quote', [ $user->user_login, $user->user_email ] );
							$keep = \preg_match( sprintf( '/(%s)/i', \implode( '|', $userParts ) ), $log[ 'message' ] ) > 0;
						}
						return $keep;
					}
				)
			);

			if ( !empty( $exportData ) ) {
				$exportItems[] = [
					'group_id'          => $con->prefix( $this->cfg->slug ),
					'group_label'       => sprintf( __( '[%s] Activity Log Entries', 'wp-simple-firewall' ),
						$con->getHumanName() ),
					'group_description' => sprintf( __( '[%s] Activity Log Entries referencing the given user.', 'wp-simple-firewall' ),
						$con->getHumanName() ),
					'item_id'           => $con->prefix( 'audit-trail' ),
					'data'              => $exportData,
				];
			}
		}

		return \is_array( $exportItems ) ? $exportItems : [];
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
				$deleter = self::con()->db_con->dbhActivityLogsMeta()->getQueryDeleter();
				$deleter->addWhereEquals( 'meta_key', 'uid' )
						->addWhereEquals( 'meta_data', $user->ID )
						->query();
				$deleter->addWhereEquals( 'meta_key', 'user_login' )
						->addWhereEquals( 'meta_data', $user->user_login )
						->query();
				$deleter->addWhereEquals( 'meta_key', 'email' )
						->addWhereEquals( 'meta_data', $user->user_email )
						->query();
				$data[ 'messages' ][] = sprintf( '%s Audit Entries deleted', self::con()->getHumanName() );
			}
		}
		catch ( \Exception $e ) {
		}
		return $data;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_Logs() :DB\Logs\Ops\Handler {
		return self::con()->db_con->loadDbH( 'at_logs' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_Meta() :DB\Meta\Ops\Handler {
		return self::con()->db_con->loadDbH( 'at_meta' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_Snapshots() :DB\Snapshots\Ops\Handler {
		return self::con()->db_con->loadDbH( 'snapshots' );
	}
}