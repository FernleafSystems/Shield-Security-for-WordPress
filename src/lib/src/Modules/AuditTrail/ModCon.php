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
		$this->getCon()->getModule_Plugin()->getDbH_IPs();
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
			case 'db_audit':
				( new DbTableExport() )
					->setDbHandler( $this->getDbHandler_AuditTrail() )
					->toCSV();
				break;
		}
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbHandler_AuditTrail()->isReady() && parent::isReadyToExecute();
	}

	/**
	 * @return array
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
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 *
	 * @param array  $aExportItems
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyExport( $aExportItems, $sEmail, $nPage = 1 ) {

		$oUser = Services::WpUsers()->getUserByEmail( $sEmail );

		$aExportItem = [
			'group_id'    => $this->prefix(),
			'group_label' => sprintf( __( '[%s] Audit Trail Entries', 'wp-simple-firewall' ), $this->getCon()
																								   ->getHumanName() ),
			'item_id'     => $this->prefix( 'audit-trail' ),
			'data'        => [],
		];

		try {
			/** @var Shield\Databases\AuditTrail\Select $oFinder */
			$oFinder = $this->getDbHandler_AuditTrail()->getQuerySelector();
			$oFinder->filterByUsername( $oUser->user_login );

			$WP = Services::WpGeneral();
			/** @var Shield\Databases\AuditTrail\EntryVO $entry */
			foreach ( $oFinder->query() as $entry ) {
				$aExportItem[ 'data' ][] = [
					$sTimeStamp = $WP->getTimeStringForDisplay( $entry->getCreatedAt() ),
					'name'  => sprintf( '[%s] Audit Trail Entry', $sTimeStamp ),
					'value' => sprintf( '[IP:%s] %s', $entry->ip, $entry->message )
				];
			}

			if ( !empty( $aExportItem[ 'data' ] ) ) {
				$aExportItems[] = $aExportItem;
			}
		}
		catch ( \Exception $e ) {
		}

		return $aExportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 *
	 * @param array  $aData
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyErase( $aData, $sEmail, $nPage = 1 ) {
		try {
			$oThisUsername = Services::WpUsers()->getUserByEmail( $sEmail )->user_login;
			$this->getDbHandler_AuditTrail()
				 ->getQueryDeleter()
				 ->addWhereSearch( 'wp_username', $oThisUsername )
				 ->all();
			$aData[ 'messages' ][] = sprintf( '%s Audit Entries deleted', $this->getCon()->getHumanName() );
		}
		catch ( \Exception $e ) {
		}
		return $aData;
	}
}