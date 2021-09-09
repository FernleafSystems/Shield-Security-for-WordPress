<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\DbTableExport;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

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
	 * @deprecated 12.0
	 */
	public function getDbHandler_AuditTrail() :Shield\Databases\AuditTrail\Handler {
		return $this->getDbH( 'audit_trail' );
	}

	public function getAuditLogger() :Lib\AuditLogger {
		if ( !isset( $this->auditLogger ) ) {
			$this->auditLogger = new Lib\AuditLogger( $this->getCon() );
		}
		return $this->auditLogger;
	}

	protected function handleFileDownload( string $downloadID ) {
		switch ( $downloadID ) {
			case 'db_log':
				Services::Response()->downloadStringAsFile(
					( new Lib\Utility\GetLogFileContent() )
						->setMod( $this )
						->run(),
					sprintf( 'log_file-%s.json', date( 'Ymd_His' ) )
				);
				break;
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbH_Logs()->isReady() && parent::isReadyToExecute();
	}

	/**
	 * TODO: This requires some fairly convoluted SQL to pick out records for a specific user in an efficient manner
	 * @param array  $exportItems
	 * @param string $email
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyExport( $exportItems, $email, $nPage = 1 ) :array {

		$user = Services::WpUsers()->getUserByEmail( $email );
		if ( !empty( $user ) ) {

			$WP = Services::WpGeneral();
			$exportData = array_map(
				function ( $log ) use ( $WP ) {
					return [
						'name'  => sprintf( '[%s] Audit Trail Entry', $WP->getTimeStringForDisplay( $log[ 'created_at' ] ) ),
						'value' => sprintf( '[IP:%s] %s', $log[ 'ip' ], $log[ 'message' ] )
					];
				},
				array_filter( // Get all logs entries pertaining to this user:
					( new Shield\Modules\AuditTrail\Lib\LogTable\LoadRawTableData() )
						->setMod( $this )
						->loadForLogs(),
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
					'group_id'    => $this->prefix(),
					'group_label' => sprintf( __( '[%s] Audit Trail Entries', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() ),
					'item_id'     => $this->prefix( 'audit-trail' ),
					'data'        => $exportData,
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

	/**
	 * @return array
	 * @deprecated 12.0 (Shield Central?)
	 */
	public function getAllContexts() {
		return [
			'all'       => 'All', //special
			'wpsf'      => $this->getCon()->getHumanName(),
			'wordpress' => 'WordPress',
			'users'     => 'Users',
			'posts'     => 'Posts',
			'plugins'   => 'Plugins',
			'themes'    => 'Themes',
			'emails'    => 'Emails',
		];
	}

	/**
	 * @inheritDoc
	 * @deprecated 12.0
	 */
	public function getDbHandlers( $bInitAll = false ) {
		return [];
	}

	/**
	 * @deprecated 12.0
	 */
	protected function cleanupDatabases() {
	}
}