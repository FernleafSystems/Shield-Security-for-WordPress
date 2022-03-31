<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class Processor extends BaseShield\Processor {

	/**
	 * @var Events\Lib\StatsWriter
	 */
	private $oStatsWriter;

	protected function run() {
		$this->loadStatsWriter()->setIfCommit( true );
		add_action( $this->getCon()->prefix( 'dashboard_widget_content' ), [ $this, 'statsWidget' ], 10 );
	}

	public function loadStatsWriter() :Events\Lib\StatsWriter {
		if ( !isset( $this->oStatsWriter ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$this->oStatsWriter = ( new Events\Lib\StatsWriter( $this->getCon() ) )
				->setDbHandler( $mod->getDbHandler_Events() );
		}
		return $this->oStatsWriter;
	}

	public function statsWidget() {
		/** @var Databases\Events\Select $selector */
		$selector = $this->getCon()
						 ->getModule_Events()
						 ->getDbHandler_Events()
						 ->getQuerySelector();

		$keyStats = [
			'comments'          => [
				__( 'Comment Blocks', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvents( [
					'spam_block_bot',
					'spam_block_human',
					'spam_block_recaptcha'
				] )
			],
			'firewall'          => [
				__( 'Firewall Blocks', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvent( 'firewall_block' )
			],
			'login_fail'        => [
				__( 'Login Blocks', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvent( 'login_block' )
			],
			'login_verified'    => [
				__( 'Login Verified', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvent( '2fa_success' )
			],
			'session_start'     => [
				__( 'User Sessions', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvent( 'session_start' )
			],
			'ip_killed'         => [
				__( 'IP Blocks', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvent( 'conn_kill' )
			],
			'ip_transgressions' => [
				__( 'Total Offenses', 'wp-simple-firewall' ),
				$selector->clearWheres()->sumEvent( 'ip_offense' )
			],
		];

		echo $this->getMod()->renderTemplate(
			'snippets/widget_dashboard_statistics.php',
			[
				'heading'  => sprintf( __( '%s Statistics', 'wp-simple-firewall' ), $this->getCon()->getHumanName() ),
				'keyStats' => $keyStats,
			]
		);
	}

	public function runDailyCron() {
		( new Events\Consolidate\ConsolidateAllEvents() )
			->setMod( $this->getMod() )
			->run();
	}
}