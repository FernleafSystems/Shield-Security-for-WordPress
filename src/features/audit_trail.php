<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return false|Shield\Databases\AuditTrail\Handler
	 */
	public function getDbHandler_AuditTrail() {
		return $this->getDbH( 'audit' );
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
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		/** @var AuditTrail\Options $oOpts */
		$oOpts = $this->getOptions();

		$aThis = [
			'strings'      => [
				'title' => __( 'Activity Audit Log', 'wp-simple-firewall' ),
				'sub'   => __( 'Track Activity: What, Who, When, Where', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aAudit = [];
			$aNonAudit = [];
			$oOpts->isAuditShield() ? $aAudit[] = 'Shield' : $aNonAudit[] = 'Shield';
			$oOpts->isAuditUsers() ? $aAudit[] = __( 'users', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'users', 'wp-simple-firewall' );
			$oOpts->isAuditPlugins() ? $aAudit[] = __( 'plugins', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'plugins', 'wp-simple-firewall' );
			$oOpts->isAuditThemes() ? $aAudit[] = __( 'themes', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'themes', 'wp-simple-firewall' );
			$oOpts->isAuditPosts() ? $aAudit[] = __( 'posts', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'posts', 'wp-simple-firewall' );
			$oOpts->isAuditEmails() ? $aAudit[] = __( 'emails', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'emails', 'wp-simple-firewall' );
			$oOpts->isAuditWp() ? $aAudit[] = 'WP' : $aNonAudit[] = 'WP';

			if ( empty( $aNonAudit ) ) {
				$aThis[ 'key_opts' ][ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'enabled' => true,
					'summary' => __( 'All important events on your site are being logged', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			elseif ( empty( $aAudit ) ) {
				$aThis[ 'key_opts' ][ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'enabled' => false,
					'summary' => sprintf( __( 'No areas are set to be audited: %s', 'wp-simple-firewall' ), implode( ', ', $aAudit ) ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			else {
				$aThis[ 'key_opts' ][ 'nonaudit' ] = [
					'name'    => __( 'Audit Events', 'wp-simple-firewall' ),
					'enabled' => false,
					'summary' => sprintf( __( "Important events aren't being audited: %s", 'wp-simple-firewall' ), implode( ', ', $aNonAudit ) ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}

			$aThis[ 'key_opts' ][ 'length' ] = [
				'name'    => __( 'Audit Trail', 'wp-simple-firewall' ),
				'enabled' => true,
				'summary' => sprintf( __( 'Maximum Audit Trail entries limited to %s', 'wp-simple-firewall' ), $oOpts->getMaxEntries() ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'audit_trail_max_entries' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'AuditTrail';
	}
}