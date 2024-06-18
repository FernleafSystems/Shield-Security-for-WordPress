<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Capabilities {

	use PluginControllerConsumer;

	public function canActivityLogsSendToFile() :bool {
		return $this->hasCap( 'activity_logs_send_to_file' ) || $this->canActivityLogsSendToIntegrations();
	}

	public function canActivityLogsSendToIntegrations() :bool {
		return $this->hasCap( 'activity_logs_send_to_integrations' );
	}

	public function canActivityLogsIntegrations() :bool {
		return $this->hasCap( 'activity_logs_integrations' );
	}

	public function canCustomSecurityRules() :bool {
		return $this->hasCap( 'custom_security_rules' );
	}

	public function canRunShieldAddons() :bool {
		return $this->hasCap( 'run_addons' );
	}

	public function canBotsAdvancedBlocking() :bool {
		return $this->hasCap( 'bots_advanced_blocking' );
	}

	public function canCrowdsecLevel1() :bool {
		return $this->hasCap( 'crowdsec_level_1' ) || $this->canCrowdsecLevel2();
	}

	public function canCrowdsecLevel2() :bool {
		return $this->hasCap( 'crowdsec_level_2' ) || $this->canCrowdsecLevel3();
	}

	public function canCrowdsecLevel3() :bool {
		return $this->hasCap( 'crowdsec_level_3' );
	}

	public function canImportExportFile() :bool {
		return $this->hasCap( 'import_export_level_1' );
	}

	public function canImportExportSync() :bool {
		return $this->hasCap( 'import_export_level_2' );
	}

	public function canLogin2faBackupCodes() :bool {
		return $this->hasCap( '2fa_login_backup_codes' );
	}

	public function canLogin2faCustomPages() :bool {
		return $this->hasCap( '2fa_custom_pages' );
	}

	public function canLogin2faRememberMe() :bool {
		return $this->hasCap( '2fa_remember_me' );
	}

	public function canHttpHeadersCSP() :bool {
		return $this->hasCap( 'http_headers_csp' );
	}

	public function canScanAllFiles() :bool {
		return $this->hasCap( 'scan_files_everywhere' );
	}

	public function canScanAutoFileRepair() :bool {
		return $this->hasCap( 'scan_auto_repair' );
	}

	public function canScanMalwareLocal() :bool {
		return $this->hasCap( 'scan_malware_local' ) || $this->canScanMalwareMalai();
	}

	public function canScanMalwareMalai() :bool {
		return $this->hasCap( 'scan_malware_malai' );
	}

	public function canScanPluginsThemesLocal() :bool {
		return $this->hasCap( 'scan_pluginsthemes_local' ) || $this->canScanPluginsThemesRemote();
	}

	public function canScanPluginsThemesRemote() :bool {
		return $this->hasCap( 'scan_pluginsthemes_remote' );
	}

	public function canMainwpLevel1() :bool {
		return $this->hasCap( 'mainwp_level_1' ) || $this->canMainwpLevel2();
	}

	public function canMainwpLevel2() :bool {
		return $this->hasCap( 'mainwp_level_2' );
	}

	public function canReportsLocal() :bool {
		return $this->hasCap( 'reports_local' ) || $this->canReportsRemote();
	}

	public function canReportsRemote() :bool {
		return $this->hasCap( 'reports_remote' );
	}

	/**
	 * Can: Check for pro license
	 */
	public function canRestAPILevel1() :bool {
		return $this->hasCap( 'rest_api_level_1' ) || $this->canRestAPILevel2();
	}

	/**
	 * Can: Full use of API
	 */
	public function canRestAPILevel2() :bool {
		return $this->hasCap( 'rest_api_level_2' );
	}

	public function canSiteBlockdown() :bool {
		return $this->hasCap( 'site_blockdown' );
	}

	public function canThirdPartyScanSpam() :bool {
		return $this->hasCap( 'thirdparty_scan_spam' );
	}

	public function canThirdPartyScanUsers() :bool {
		return $this->hasCap( 'thirdparty_scan_users' );
	}

	public function canThirdPartyActivityLog() :bool {
		return $this->hasCap( 'thirdparty_activity_logs' );
	}

	public function canTrafficRateLimit() :bool {
		return $this->hasCap( 'traffic_rate_limiting' );
	}

	public function canUserPasswordPolicies() :bool {
		return $this->hasCap( 'user_password_policies' );
	}

	public function canUserSuspend() :bool {
		return $this->hasCap( 'user_suspension' );
	}

	public function canUserAutoUnblock() :bool {
		return $this->hasCap( 'user_auto_unblock' );
	}

	public function canUserBlockSpamReg() :bool {
		return $this->hasCap( 'user_block_spam_registration' );
	}

	public function canWhitelabel() :bool {
		return $this->hasCap( 'whitelabel' );
	}

	/**
	 * Can: Check for pro license
	 */
	public function canWpcliLevel1() :bool {
		return $this->hasCap( 'wpcli_level_1' ) || $this->canWpcliLevel2();
	}

	/**
	 * Can: Full use of WP-CLI
	 */
	public function canWpcliLevel2() :bool {
		return $this->hasCap( 'wpcli_level_2' );
	}

	public function hasCap( string $cap ) :bool {
		$license = self::con()->comps->license->getLicense();
		return !$this->isPremiumOnlyCap( $cap )
			   || (
				   self::con()->isPremiumActive()
				   && ( \in_array( $cap, $license->capabilities, true ) || $license->lic_version === 0 )
			   );
	}

	public function getMaxLogRetentionDays() :int {
		if ( $this->hasCap( 'logs_retention_unlimited' ) ) {
			$max = \PHP_INT_MAX;
		}
		elseif ( $this->hasCap( 'logs_retention_31' ) ) {
			$max = 31;
		}
		else {
			$max = self::con()->cfg->configuration->def( 'max_free_days' );
		}
		return $max;
	}

	private function isPremiumOnlyCap( string $cap ) :bool {
		return \in_array( $cap, [
			'2fa_login_backup_codes',
			'2fa_multi_yubikey',
			'2fa_u2f',
			'2fa_sure_send',
			'2fa_email_any_user',
			'2fa_webauthn',
			'2fa_remember_me',
			'2fa_custom_pages', // No option?
			'activity_logs_send_to_file',
			'activity_logs_send_to_integrations',
			'activity_logs_integrations',
			'logs_retention_31',
			'logs_retention_unlimited',
			'bots_advanced_blocking',
			'crowdsec_level_1',
			'crowdsec_level_2',
			'crowdsec_level_3',
			'custom_security_rules',
			'run_addons',
			'http_headers_csp',
			'import_export_level_1',
			'import_export_level_2',
			'instant_alerts',
			'ips_import_from_file',
			'scan_malware_local',
			'scan_malware_malai',
			'scan_pluginsthemes_local',
			'scan_pluginsthemes_remote',
			'scan_files_everywhere',
			'scan_file_locker',
			'scan_frequent',
			'scan_auto_repair',
			'scan_vulnerabilities',
			'scan_vulnerabilities_autoupdate',
			'site_blockdown',
			'thirdparty_scan_spam',
			'thirdparty_scan_users',
			'thirdparty_activity_logs',
			'traffic_rate_limiting',
			'traffic_live_log',
			'mainwp_level_1',
			'mainwp_level_2',
			'rest_api_level_2',
			'run_as_mu',
			'user_password_policies',
			'user_suspension',
			'user_auto_unblock',
			'user_block_spam_registration',
			'whitelabel',
			'wpcli_level_2',
			'reports_local',
			'reports_remote',
		] );
	}
}