<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Statistics extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 */
	public function isModuleEnabled() {
		return false;
	}

	/**
	 */
	protected function updateHandler() {
		$this->updateHandler_ConvertStats();
	}

	private function updateHandler_ConvertStats() {
		$aMap = [
			'firewall.blocked.dirtraversal'    => 'blockparam_dirtraversal',
			'firewall.blocked.sqlqueries'      => 'blockparam_sqlqueries',
			'firewall.blocked.wpterms'         => 'blockparam_wpterms',
			'firewall.blocked.fieldtruncation' => 'blockparam_fieldtruncation',
			'firewall.blocked.phpcode'         => 'blockparam_phpcode',
			'firewall.blocked.schema'          => 'blockparam_schema',
			'firewall.blocked.aggressive'      => 'blockparam_aggressive',
			'firewall.blocked.exefile'         => 'block_exefile',
			'ip.connection.killed'             => 'conn_kill',
			'ip.transgression.incremented'     => 'ip_offense',
			'login.rename.fail'                => 'hide_login_url',
			'user.session.start'               => 'session_start',
			'user.session.terminate'           => 'session_terminate',
			'login.recaptcha.verified'         => 'recaptcha_success',
			'login.recaptcha.fail'             => 'recaptcha_fail',
			'login.cooldown.fail'              => 'block_exefile',
			'login.twofactor.verified'         => '2fa_success',
		];
		// TODO: Count all firewall.blocked.*

		/** @var Shield\Databases\Tally\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		/** @var Shield\Databases\Tally\Select $oSelectTally */
		$oSelectTally = $oDbH->getQuerySelector();

		/** @var Shield\Databases\Tally\EntryVO[] $aAll */
		$aAll = $oSelectTally->all();

		$aNewEvents = [
			'firewall_block'       => 0,
			'botbox_fail'          => 0,
			'honeypot_fail'        => 0,
			'spam_block_bot'       => 0,
			'spam_block_recaptcha' => 0,
			'spam_block_human'     => 0,
			'login_block'          => 0,
		];
		foreach ( $aAll as $oTally ) {
			if ( isset( $aMap[ $oTally->stat_key ] ) ) {
				$aNewEvents[ $aMap[ $oTally->stat_key ] ] = $oTally->tally;
			}
			if ( strpos( $oTally->stat_key, 'firewall.blocked.' ) === 0 ) {
				$aNewEvents[ 'firewall_block' ] += $oTally->tally;
			}
			if ( strpos( $oTally->stat_key, 'gasp.checkbox.fail' ) === 0 ) {
				$aNewEvents[ 'botbox_fail' ] += $oTally->tally;
			}
			if ( strpos( $oTally->stat_key, 'gasp.honeypot.fail' ) === 0 ) {
				$aNewEvents[ 'honeypot_fail' ] += $oTally->tally;
			}
			if ( strpos( $oTally->stat_key, 'spam.gasp.' ) === 0 ) {
				$aNewEvents[ 'spam_block_bot' ] += $oTally->tally;
			}
			if ( strpos( $oTally->stat_key, 'spam.recaptcha.' ) === 0 ) {
				$aNewEvents[ 'spam_block_recaptcha' ] += $oTally->tally;
			}
			if ( strpos( $oTally->stat_key, 'spam.human.' ) === 0 ) {
				$aNewEvents[ 'spam_block_human' ] += $oTally->tally;
			}
			if ( preg_match( '#login.*fail#', $oTally->stat_key ) ) {
				$aNewEvents[ 'login_block' ] += $oTally->tally;
			}
		}

		/** @var Shield\Databases\Events\Handler $oDbhEvents */
		$oDbhEvents = $this->getCon()->getModule_Events()->getDbHandler();
		$nTs = Services::Request()
					   ->carbon()
					   ->subYear( 1 )->timestamp;
		foreach ( array_filter( $aNewEvents ) as $sEvent => $nTally ) {
			$oDbhEvents->commitEvent( $sEvent, $nTally, $nTs );
		}

		/** @var Shield\Databases\Tally\Handler $oDbhTallys */
		$oDbhTallys = $this->getDbHandler();
		/** @var Shield\Databases\Tally\Delete $oDelTallys */
		$oDelTallys = $oDbhTallys->getQueryDeleter();
		foreach ( $aAll as $oTally ) {
			$oDelTallys->deleteEntry( $oTally );
		}
	}

	/**
	 * @return Shield\Databases\Tally\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Tally\Handler();
	}

	/**
	 * @return Shield\Modules\Statistics\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Statistics\Options();
	}

	/**
	 * @return Shield\Modules\Statistics\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Statistics\Strings();
	}
}