<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Changelog\Retrieve;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\UI $uiReporting */
		$uiReporting = $con->getModule_Reporting()->getUIHandler();

		return [
			'content' => [
				'tab_updates'   => $this->renderTabUpdates(),
				'tab_freetrial' => $this->renderFreeTrial(),
				'summary_stats' => $uiReporting->renderSummaryStats()
			],
			'vars'    => [
				'overview_cards' => ( new OverviewCards() )
					->setMod( $this->getMod() )
					->buildForShuffle(),
			],
			'hrefs'   => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
			],
			'flags'   => [
				'show_ads'              => false,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
			],
			'strings' => [
				'tab_security_glance' => __( 'Security At A Glance', 'wp-simple-firewall' ),
				'tab_freetrial'       => __( 'Free Trial', 'wp-simple-firewall' ),
				'tab_updates'         => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_summary_stats'   => __( 'Summary Stats', 'wp-simple-firewall' ),
				'click_clear_filter'  => __( 'Click To Filter By Security Area or Status', 'wp-simple-firewall' ),
				'discover'            => __( 'Discover where your site security is doing well or areas that can be improved', 'wp-simple-firewall' ),
				'clear_filter'        => __( 'Clear Filter', 'wp-simple-firewall' ),
				'click_to_toggle'     => __( 'click to toggle', 'wp-simple-firewall' ),
				'go_to_options'       => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'key'                 => __( 'Key' ),
				'key_positive'        => __( 'Positive Security', 'wp-simple-firewall' ),
				'key_warning'         => __( 'Potential Warning', 'wp-simple-firewall' ),
				'key_danger'          => __( 'Potential Danger', 'wp-simple-firewall' ),
				'key_information'     => __( 'Information', 'wp-simple-firewall' ),
			],
		];
	}

	private function renderFreeTrial() :string {
		$user = Services::WpUsers()->getCurrentWpUser();
		return $this->getMod()
					->renderTemplate(
						'/forms/drip_trial_signup.twig',
						[
							'vars'    => [
								// the keys here must match the changelog item types
								'activation_url' => Services::WpGeneral()->getHomeUrl(),
								'email'          => $user->user_email,
								'name'           => $user->user_firstname,
							],
							'strings' => [
							],
						],
						true
					);
	}

	private function renderTabUpdates() :string {
		try {
			$changelog = ( new Retrieve() )
				->setCon( $this->getCon() )
				->fromRepo();
		}
		catch ( \Exception $e ) {
			$changelog = ( new Retrieve() )
				->setCon( $this->getCon() )
				->fromFile();
		}

		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/overview/updates/index.twig',
						[
							'vars'      => [
								// the keys here must match the changelog item types
								'badge_types' => [
									'new'      => 'primary',
									'added'    => 'light',
									'improved' => 'info',
									'changed'  => 'warning',
									'fixed'    => 'danger',
								]
							],
							'strings'   => [
								// the keys here must match the changelog item types
								'version'      => __( 'Version', 'wp-simple-firewall' ),
								'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
								'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
								'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
							],
							'changelog' => $changelog
						],
						true
					);
	}
}