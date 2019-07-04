<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

class ICWP_WPSF_Processor_Events extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var bool
	 */
	private $bStat = false;

	public function run() {
		$this->bStat = true;
		if ( $this->isReadyToRun() ) {
			add_filter( $this->getMod()->prefix( 'dashboard_widget_content' ), [
				$this,
				'gatherStatsWidgetContent'
			], 10 );
		}
	}

	public function gatherStatsWidgetContent( $aContent ) {
		/** @var Shield\Databases\Events\Handler $oDbhEvents */
		$oDbhEvents = $this->getCon()->getModule_Events()->getDbHandler();
		/** @var Shield\Databases\Events\Select $oSelEvents */
		$oSelEvents = $oDbhEvents->getQuerySelector();

		$aKeyStats = [
			'comments'          => [
				__( 'Comment Blocks', 'wp-simple-firewall' ),
				$oSelEvents->sumEvents( [ 'spam_block_bot', 'spam_block_human', 'spam_block_recaptcha' ] )
			],
			'firewall'          => [
				__( 'Firewall Blocks', 'wp-simple-firewall' ),
				$oSelEvents->sumEvent( 'firewall_block' )
			],
			'login_fail'        => [
				__( 'Login Blocks', 'wp-simple-firewall' ),
				$oSelEvents->sumEvent( 'login_block' )
			],
			'login_verified'    => [
				__( 'Login Verified', 'wp-simple-firewall' ),
				$oSelEvents->sumEvent( '2fa_success' )
			],
			'start_session'     => [
				__( 'User Sessions', 'wp-simple-firewall' ),
				$oSelEvents->sumEvent( 'start_session' )
			],
			'ip_killed'         => [
				__( 'IP Blocks', 'wp-simple-firewall' ),
				$oSelEvents->sumEvent( 'conn_kill' )
			],
			'ip_transgressions' => [
				__( 'Total Offenses', 'wp-simple-firewall' ),
				$oSelEvents->sumEvent( 'ip_offense' )
			],
		];

		$aDisplayData = [
			'sHeading'  => sprintf( __( '%s Statistics', 'wp-simple-firewall' ), $this->getCon()->getHumanName() ),
			'aKeyStats' => $aKeyStats,
		];

		if ( !is_array( $aContent ) ) {
			$aContent = [];
		}
		$aContent[] = $this->getMod()
						   ->renderTemplate(
							   'snippets/widget_dashboard_statistics.php',
							   $aDisplayData
						   );
		return $aContent;
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		/** @var Shield\Databases\Events\Handler $oDbhEvents */
		$oDbhEvents = $this->getMod()->getDbHandler();
		/** @var Shield\Databases\Events\Select $oSelEvents */
		$oSelEvents = $oDbhEvents->getQuerySelector();

		$aData = parent::tracking_DataCollect( $aData );
		$aData[ $this->getMod()->getSlug() ][ 'stats' ] = $oSelEvents->sumAllEvents();
		return $aData;
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( $this->bStat && !$this->getCon()->isPluginDeleting() ) {
			$this->commitEvents();
		}
	}

	/**
	 */
	private function commitEvents() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var Events\Handler $oDbh */
		$oDbh = $oMod->getDbHandler();
		$oDbh->commitEvents( $oMod->getRegisteredEvents( true ) );
	}
}