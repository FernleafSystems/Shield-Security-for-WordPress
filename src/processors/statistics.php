<?php

class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
		$oFO = $this->getMod();
		if ( $this->isReadyToRun() ) {
			add_filter( $oFO->prefix( 'dashboard_widget_content' ), [ $this, 'gatherStatsWidgetContent' ], 10 );
		}
		$this->getTallyProcessor()->run();
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$aTallys = $this->getAllTallys();
		$aTallyTracking = [];
		foreach ( $aTallys as $oTally ) {
			$sKey = preg_replace( '#[^_a-z]#', '', str_replace( '.', '_', $oTally->stat_key ) );
			if ( strpos( $sKey, '_' ) ) {
				$aTallyTracking[ $sKey ] = (int)$oTally->tally;
			}
		}
		$aData[ $this->getMod()->getSlug() ][ 'stats' ] = $aTallyTracking;
		return $aData;
	}

	/**
	 * @return array
	 */
	public function getInsightsStats() {
		$aAllTallys = $this->getAllTallys();
		$aAllStats = [];

		$aSpamCommentKeys = [
			'spam.gasp.checkbox',
			'spam.gasp.token',
			'spam.gasp.honeypot',
			'spam.recaptcha.empty',
			'spam.recaptcha.failed',
			'spam.human.comment_content',
			'spam.human.url',
			'spam.human.author_name',
			'spam.human.author_email',
			'spam.human.ip_address',
			'spam.human.user_agent'
		];
		$aLoginFailKeys = [
			'login.cooldown.fail',
			'login.recaptcha.fail',
			'login.gasp.checkbox.fail',
			'login.gasp.honeypot.fail',
			'login.googleauthenticator.fail',
			'login.rename.fail',
		];
		$aLoginVerifiedKeys = [
			'login.googleauthenticator.verified',
			'login.recaptcha.verified',
			'login.twofactor.verified'
		];

		$aAllStats[ 'ip.transgression.incremented' ] = 0;
		$aAllStats[ 'ip.connection.killed' ] = 0;
		$aAllStats[ 'comments.blocked.all' ] = 0;
		$aAllStats[ 'firewall.blocked.all' ] = 0;
		$aAllStats[ 'login.blocked.all' ] = 0;
		$aAllStats[ 'login.verified.all' ] = 0;
		$aAllStats[ 'login.verified.all' ] = 0;

		foreach ( $aAllTallys as $oStat ) {
			$sStatKey = $oStat->stat_key;
			$nTally = $oStat->tally;

			if ( in_array( $sStatKey, $aSpamCommentKeys ) ) {
				$aAllStats[ 'comments.blocked.all' ] += $nTally;
			}
			else if ( strpos( $sStatKey, 'firewall.blocked.' ) !== false ) {
				$aAllStats[ 'firewall.blocked.all' ] += $nTally;
			}
			else if ( in_array( $sStatKey, $aLoginFailKeys ) ) {
				$aAllStats[ 'login.blocked.all' ] += $nTally;
			}
			else if ( $sStatKey == 'ip.connection.killed' ) {
				$aAllStats[ 'ip.connection.killed' ] += $nTally;
			}
			else if ( $sStatKey == 'ip.transgression.incremented' ) {
				$aAllStats[ 'ip.transgression.incremented' ] += $nTally;
			}
			else if ( $sStatKey == 'user.session.start' ) {
				$nTotalUserSessionsStarted = $nTally;
			}
			else if ( $sStatKey == 'file.corechecksum.replaced' ) {
			}
			else if ( in_array( $sStatKey, $aLoginVerifiedKeys ) ) {
				$aAllStats[ 'login.verified.all' ] += $nTally;
			}
		}

		return array_merge(
			[
				'ip.transgression.incremented' => 0,
				'ip.connection.killed'         => 0,
				'firewall.blocked.all'         => 0,
				'comments.blocked.all'         => 0,
				'login.blocked.all'            => 0,
				'login.verified.all'           => 0,
			],
			$aAllStats
		);
	}

	public function gatherStatsWidgetContent( $aContent ) {
		/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
		$oFO = $this->getMod();

		$aAllStats = $this->getAllTallys();
		$nTotalCommentSpamBlocked = 0;
		$nTotalLoginBlocked = 0;
		$nTotalLoginVerified = 0;
		$nTotalFirewallBlocked = 0;
		$nTotalConnectionKilled = 0;
		$nTotalTransgressions = 0;
		$nTotalUserSessionsStarted = 0;
//			$nTotalFilesReplaced = 0;

		$aSpamCommentKeys = [
			'spam.gasp.checkbox',
			'spam.gasp.token',
			'spam.gasp.honeypot',
			'spam.recaptcha.empty',
			'spam.recaptcha.failed',
			'spam.human.comment_content',
			'spam.human.url',
			'spam.human.author_name',
			'spam.human.author_email',
			'spam.human.ip_address',
			'spam.human.user_agent'
		];
		$aLoginFailKeys = [
			'login.cooldown.fail',
			'login.recaptcha.fail',
			'login.gasp.checkbox.fail',
			'login.gasp.honeypot.fail',
			'login.googleauthenticator.fail',
			'login.rename.fail',
		];
		$aLoginVerifiedKeys = [
			'login.googleauthenticator.verified',
			'login.recaptcha.verified',
			'login.twofactor.verified'
		];
		foreach ( $aAllStats as $oStat ) {
			$sStatKey = $oStat->stat_key;
			$nTally = $oStat->tally;

			if ( in_array( $sStatKey, $aSpamCommentKeys ) ) {
				$nTotalCommentSpamBlocked += $nTally;
			}
			else if ( strpos( $sStatKey, 'firewall.blocked.' ) !== false ) {
				$nTotalFirewallBlocked += $nTally;
			}
			else if ( in_array( $sStatKey, $aLoginFailKeys ) ) {
				$nTotalLoginBlocked += $nTally;
			}
			else if ( $sStatKey == 'ip.connection.killed' ) {
				$nTotalConnectionKilled = $nTally;
			}
			else if ( $sStatKey == 'ip.transgression.incremented' ) {
				$nTotalTransgressions = $nTally;
			}
			else if ( $sStatKey == 'user.session.start' ) {
				$nTotalUserSessionsStarted = $nTally;
			}
			else if ( $sStatKey == 'file.corechecksum.replaced' ) {
				$nTotalUserSessionsStarted = $nTally;
			}
			else if ( in_array( $sStatKey, $aLoginVerifiedKeys ) ) {
				$nTotalLoginVerified += $nTally;
			}
		}

		$aKeyStats = [
			'comments'          => [ __( 'Comment Blocks', 'wp-simple-firewall' ), $nTotalCommentSpamBlocked ],
			'firewall'          => [ __( 'Firewall Blocks', 'wp-simple-firewall' ), $nTotalFirewallBlocked ],
			'login_fail'        => [ __( 'Login Blocks', 'wp-simple-firewall' ), $nTotalLoginBlocked ],
			'login_verified'    => [ __( 'Login Verified', 'wp-simple-firewall' ), $nTotalLoginVerified ],
			'session_start'     => [ __( 'User Sessions', 'wp-simple-firewall' ), $nTotalUserSessionsStarted ],
			'ip_killed'         => [ __( 'IP Auto Black-Listed', 'wp-simple-firewall' ), $nTotalConnectionKilled ],
			'ip_transgressions' => [ __( 'Total Offenses', 'wp-simple-firewall' ), $nTotalTransgressions ],
		];

		$aDisplayData = [
			'sHeading'  => sprintf( __( '%s Statistics', 'wp-simple-firewall' ), $this->getCon()->getHumanName() ),
			'aAllStats' => $aAllStats,
			'aKeyStats' => $aKeyStats,
		];

		if ( !is_array( $aContent ) ) {
			$aContent = [];
		}
		$aContent[] = $oFO->renderTemplate( 'snippets/widget_dashboard_statistics.php', $aDisplayData );
		return $aContent;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally\EntryVO[]
	 */
	protected function getAllTallys() {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally\EntryVO[] $aRes */
		$aRes = $this->getMod()
					 ->getDbHandler()
					 ->getQuerySelector()
					 ->setColumnsToSelect( [ 'stat_key', 'tally' ] )
					 ->query();
		return $aRes;
	}

	/**
	 * @return \ICWP_WPSF_Processor_Statistics_Tally|mixed
	 */
	protected function getTallyProcessor() {
		return $this->getSubPro( 'tally' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'tally'  => 'ICWP_WPSF_Processor_Statistics_Tally',
		];
	}
}