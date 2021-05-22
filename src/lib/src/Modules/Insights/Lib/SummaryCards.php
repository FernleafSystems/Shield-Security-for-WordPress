<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SummaryCards {

	use ModConsumer;

	public function build() :array {
		$cards = [];
		$cards = array_merge(
			$cards,
			$this->getLoginSummary(),
			$this->getFirewallSummary(),
			$this->getHackguardSummary(),
			$this->getSecurityAdminSummary(),
			$this->getIPBlockingSummary(),
			$this->getCommentSpamSummary(),
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
			]
		];
	}

	private function getCommentSpamSummary() :array {
		$mod = $this->getCon()->getModule_Comments();
		/** @var Modules\CommentsFilter\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => 'SPAM',
				'enabled' => $mod->isModuleEnabled()
							 && $opts->isEnabledAntiBot(),
			]
		];
	}

	private function getHackguardSummary() :array {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Modules\HackGuard\Options $opts */
		$opts = $mod->getOptions();
		return [
			$mod->getSlug() => [
				'title'   => $mod->getMainFeatureName(),
				'enabled' => $mod->isModuleEnabled(),
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
				'enabled' => $mod->isModuleEnabled()
							 && $opts->isEnabledAntiBot(),
			]
		];
	}

	private function getFirewallSummary() :array {
		return $this->getBasicModSummary( $this->getCon()->getModule_Firewall() );
	}

	private function getBasicModSummary( $mod ) :array {
		return [
			$mod->getSlug() => [
				'title'   => $mod->getMainFeatureName(),
				'enabled' => $mod->isModuleEnabled(),
			]
		];
	}
}