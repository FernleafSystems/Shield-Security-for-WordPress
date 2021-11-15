<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SummaryCards {

	use ModConsumer;

	public function build() :array {
		$cards = [];
		$cards = array_merge(
			$cards,
			$this->getLoginSummary(),
			$this->getFirewallSummary(),
			$this->getIPBlockingSummary(),
			$this->getSecurityAdminSummary(),
			$this->getCommentSpamSummary(),
			$this->getHackguardSummary(),
			$this->getUserSummary(),
			$this->getAuditTrailSummary(),
			$this->getPluginSummary()
		);

		return array_map(
			function ( $card ) {
				$card = array_merge(
					[
						'enabled' => false
					],
					$card
				);

				if ( empty( $card[ 'icon' ] ) ) {
					$card[ 'icon' ] = $card[ 'enabled' ] ?
						$this->getCon()->svgs->raw( 'bootstrap/shield-fill-check.svg' )
						: $this->getCon()->svgs->raw( 'bootstrap/shield-fill-exclamation.svg' );
				}

				return $card;
			},
			$cards
		);
	}

	private function getPluginSummary() :array {
		$mod = $this->getCon()->getModule_Plugin();
		return [
			$mod->getSlug() => [
				'title'   => 'Plugin',
				'enabled' => $mod->isModuleEnabled(),
				'href'    => $mod->getUrl_DirectLinkToOption( 'global_enable_plugin_features' ),
			]
		];
	}

	private function getCommentSpamSummary() :array {
		$mod = $this->getCon()->getModule_Comments();
		/** @var Modules\CommentsFilter\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => 'Comment SPAM',
				'enabled' => $mod->isModuleEnabled()
							 && $opts->isEnabledAntiBot(),
				'href'    => $mod->getUrl_AdminPage(),
			]
		];
	}

	private function getAuditTrailSummary() :array {
		$mod = $this->getCon()->getModule_AuditTrail();
		return [
			$mod->getSlug() => [
				'title'   => "Audit Trail",
				'enabled' => $mod->isModuleEnabled(),
				'href'    => $mod->getUrl_AdminPage(),
			]
		];
	}

	private function getUserSummary() :array {
		$mod = $this->getCon()->getModule_UserManagement();
		/** @var Modules\UserManagement\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => "'Pwned' Passwords",
				'enabled' => $mod->isModuleEnabled()
							 && $opts->isPasswordPoliciesEnabled()
							 && $opts->isPassPreventPwned(),
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_password_policies' ),
			]
		];
	}

	private function getHackguardSummary() :array {
		$mod = $this->getCon()->getModule_HackGuard();
		return [
			$mod->getSlug() => [
				'title'   => 'Scanners',
				'enabled' => $mod->isModuleEnabled(),
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_hack_protect' ),
			],
			'core_files'    => [
				'title'   => 'WordPress File Scan',
				'enabled' => $mod->isModuleEnabled()
							 && $mod->getScanCon( Afs::SCAN_SLUG )->isEnabled(),
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
			]
		];
	}

	private function getIPBlockingSummary() :array {
		$mod = $this->getCon()->getModule_IPs();
		/** @var Modules\IPs\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => 'Auto IP Block',
				'enabled' => $mod->isModuleEnabled()
							 && ( $opts->getOffenseLimit() > 0 ),
				'href'    => $mod->getUrl_AdminPage(),
			]
		];
	}

	private function getSecurityAdminSummary() :array {
		$mod = $this->getCon()->getModule_SecAdmin();
		return [
			$mod->getSlug() => [
				'title'   => $mod->getMainFeatureName(),
				'enabled' => $mod->isModuleEnabled()
							 && $mod->getSecurityAdminController()->isEnabledSecAdmin(),
				'href'    => $mod->getUrl_AdminPage(),
			]
		];
	}

	private function getLoginSummary() :array {
		$mod = $this->getCon()->getModule_LoginGuard();
		/** @var Modules\LoginGuard\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => 'Login',
				'enabled' => $mod->isModuleEnabled() && $opts->isEnabledAntiBot(),
				'href'    => $mod->getUrl_AdminPage(),
			]
		];
	}

	private function getFirewallSummary() :array {
		$mod = $this->getCon()->getModule_Firewall();
		/** @var Modules\LoginGuard\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => 'Firewall',
				'enabled' => $mod->isModuleEnabled(),
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_firewall' ),
			]
		];
	}

	/**
	 * @param Modules\Base\ModCon $mod
	 * @return array[]
	 */
	private function getBasicModSummary( $mod ) :array {
		return [
			$mod->getSlug() => [
				'title'   => $mod->getMainFeatureName(),
				'enabled' => $mod->isModuleEnabled(),
				'href'    => $mod->getUrl_AdminPage(),
			]
		];
	}
}