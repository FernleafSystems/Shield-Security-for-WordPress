<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

class ICWP_WPSF_Processor_Events extends Shield\Modules\BaseShield\ShieldProcessor {

	/**
	 * @var bool
	 */
	private $bStat = false;

	public function run() {
		$this->bStat = true;
		if ( $this->isReadyToRun() ) {
			add_filter( $this->getMod()->prefix( 'dashboard_widget_content' ), [ $this, 'statsWidget' ], 10 );
		}
	}

	/**
	 * @param string[] $aContent
	 * @return string[]
	 */
	public function statsWidget( $aContent ) {
		/** @var Events\Select $oSelEvents */
		$oSelEvents = $this->getCon()
						   ->getModule_Events()
						   ->getDbHandler_Events()
						   ->getQuerySelector();

		$aKeyStats = [
			'comments'          => [
				__( 'Comment Blocks', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvents( [
					'spam_block_bot',
					'spam_block_human',
					'spam_block_recaptcha'
				] )
			],
			'firewall'          => [
				__( 'Firewall Blocks', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvent( 'firewall_block' )
			],
			'login_fail'        => [
				__( 'Login Blocks', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvent( 'login_block' )
			],
			'login_verified'    => [
				__( 'Login Verified', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvent( '2fa_success' )
			],
			'session_start'     => [
				__( 'User Sessions', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvent( 'session_start' )
			],
			'ip_killed'         => [
				__( 'IP Blocks', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvent( 'conn_kill' )
			],
			'ip_transgressions' => [
				__( 'Total Offenses', 'wp-simple-firewall' ),
				$oSelEvents->clearWheres()->sumEvent( 'ip_offense' )
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
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();

		$aData = parent::tracking_DataCollect( $aData );
		$aData[ $oMod->getSlug() ][ 'stats' ] = $oMod->getDbHandler_Events()
													 ->getQuerySelector()
													 ->sumAllEvents();
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
		$oMod->getDbHandler_Events()
			 ->commitEvents( $oMod->getRegisteredEvents( true ) );
	}

	public function runDailyCron() {
		( new Shield\Modules\Events\Consolidate\ConsolidateAllEvents() )
			->setMod( $this->getMod() )
			->run();
	}
}