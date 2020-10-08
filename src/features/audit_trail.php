<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return false|Shield\Databases\AuditTrail\Handler
	 */
	public function getDbHandler_AuditTrail() {
		return $this->getDbH( 'audit' )
					->setTable( $this->getDef( 'audit_trail_table_name' ) );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		return ( $this->getDbHandler_AuditTrail() instanceof Shield\Databases\AuditTrail\Handler )
			   && $this->getDbHandler_AuditTrail()->isReady()
			   && parent::isReadyToExecute();
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
			$oFinder = $this->getDbHandler_AuditTrail()
							->getQuerySelector();
			$oFinder->filterByUsername( $oUser->user_login );

			$oWp = Services::WpGeneral();
			/** @var Shield\Databases\AuditTrail\EntryVO $oEntry */
			foreach ( $oFinder->query() as $oEntry ) {
				$aExportItem[ 'data' ][] = [
					$sTimeStamp = $oWp->getTimeStringForDisplay( $oEntry->getCreatedAt() ),
					'name'  => sprintf( '[%s] Audit Trail Entry', $sTimeStamp ),
					'value' => sprintf( '[IP:%s] %s', $oEntry->ip, $oEntry->message )
				];
			}

			if ( !empty( $aExportItem[ 'data' ] ) ) {
				$aExportItems[] = $aExportItem;
			}
		}
		catch ( \Exception $oE ) {
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
		catch ( \Exception $oE ) {
		}
		return $aData;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'AuditTrail';
	}
}