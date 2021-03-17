<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class DashboardCards {

	use Shield\Modules\ModConsumer;

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function renderAll() :array {
		$cards = array_merge(
			[
				'settings' => $this->renderSettingsCard()
			],
			array_map(
				function ( $card ) {
					return $this->renderStandardCard( $card );
				},
				array_filter(
					$this->buildStandardCards(),
					function ( $card ) {
						return empty( $card[ 'hidden' ] );
					}
				)
			)
		);

		if ( !empty( array_diff_key( $cards, array_flip( $this->getAllCardSlugs() ) ) ) ) {
			throw new \Exception( 'Card(s) with unrecognised slug' );
		}

		// Merge ensures the order is as we want it, and the intersect ensure hidden cards are not included
		return array_merge(
			array_flip( array_intersect( $this->getAllCardSlugs(), array_keys( $cards ) ) ),
			$cards
		);
	}

	public function renderSettingsCard() :string {
		$con = $this->getCon();
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();

		$name = $con->getHumanName();

		return $mod->renderTemplate(
			'/wpadmin_pages/insights/dashboard/card_settings.twig',
			[
				'c'       => [
					'title'   => __( 'Shield Settings', 'wp-simple-firewall' ),
					'img'     => $con->urls->forImage( 'bootstrap/sliders.svg' ),
					'introjs' => sprintf( __( "%s is a big plugin split into modules, and each with their own options - use these jump-off points to find the specific option you need.", 'wp-simple-firewall' ), $name ),
					'paras'   => [
						sprintf( __( "%s settings are arranged into modules.", 'wp-simple-firewall' ), $name )
						.' '.__( 'Choose the module you need from the dropdown.', 'wp-simple-firewall' )
					],
					'actions' => [
						[
							'text' => __( "Go To General Settings", 'wp-simple-firewall' ),
							'href' => $mod->getUrl_AdminPage(),
						],
						[
							'text' => __( "Scans & Hack Guard Settings", 'wp-simple-firewall' ),
							'href' => $con->getModule_HackGuard()->getUrl_AdminPage(),
						],
					],
				],
				'strings' => [
					'select' => __( "Select Module", 'wp-simple-firewall' )
				],
				'vars'    => [
					'mods'          => $mod->getUIHandler()->buildSelectData_ModuleSettings(),
					'search_select' => $mod->getUIHandler()->buildSelectData_OptionsSearch()
				]
			],
			true
		);
	}

	protected function renderStandardCard( array $card ) :string {
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();
		return $mod->renderTemplate(
			'/wpadmin_pages/insights/dashboard/card_std.twig',
			[ 'c' => $card ],
			true
		);
	}

	private function buildStandardCards() :array {
		$con = $this->getCon();
		$modComments = $con->getModule_Comments();
		$modInsights = $con->getModule_Insights();
		$modPlugin = $con->getModule_Plugin();

		$name = $con->getHumanName();

		/** @var AdminNotes\EntryVO $note */
		$note = $modPlugin->getDbHandler_Notes()->getQuerySelector()->first();
		$latestNote = $note instanceof AdminNotes\EntryVO ?
			sprintf( 'Your most recent note: "%s"', $note->note ) :
			__( 'No notes made yet.', 'wp-simple-firewall' );

		return [

			'overview' => [
				'title'   => __( 'Security Overview', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/binoculars.svg' ),
				'introjs' => sprintf( __( "Review your entire Shield Security configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ), $name ),
				'paras'   => [
					sprintf( __( "Review your entire %s security configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ), $name ),
				],
				'actions' => [
					[
						'text' => __( "See My Security Overview", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'overview' ),
					],
				]
			],

			'scans' => [
				'title'   => __( 'Scans and Protection', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/shield-shaded.svg' ),
				'introjs' => sprintf( __( "Run a %s scan at any time, or view the results from the latest scan.", 'wp-simple-firewall' ), $name ),
				'paras'   => [
					sprintf( __( "Use %s Scans to automatically detect and repair intrusions on your site.", 'wp-simple-firewall' ), $name ),
					sprintf( __( "%s scans WordPress core files, plugins, themes and will detect Malware (ShieldPRO).", 'wp-simple-firewall' ), $name ),
				],
				'actions' => [
					[
						'text' => __( "Run Scans", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'scans' ),
					],
					[
						'text' => __( "Scans & Hack Guard Settings", 'wp-simple-firewall' ),
						'href' => $con->getModule_HackGuard()->getUrl_AdminPage(),
					],
				]
			],

			'sec_admin' => [
				'title'   => __( 'Security Admin', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/person-badge.svg' ),
				'introjs' => sprintf( __( "Lock down access to %s itself to specific WP Administrators.", 'wp-simple-firewall' ), $name ),
				'paras'   => [
					sprintf( __( "Restrict access to %s itself and prevent unwanted changes to your site by other administrators.", 'wp-simple-firewall' ), $name ),
				],
				'actions' => [
					[
						'text' => __( "Security Admin Settings", 'wp-simple-firewall' ),
						'href' => $con->getModule_SecAdmin()->getUrl_AdminPage(),
					],
				]
			],

//			'reports' => [
//				'title'   => __( 'Reports and Stats', 'wp-simple-firewall' ),
//				'img'     => $con->urls->forImage( 'bootstrap/graph-up.svg' ),
//				'introjs' => sprintf( __( "See the effect on your site security by %s in numbers", 'wp-simple-firewall' ), $name ),
//				'paras'   => [
//					sprintf( __( "Display charts to see how %s is performing over time and in which areas your site has been most impacted.", 'wp-simple-firewall' ), $name ),
//				],
//				'actions' => [
//					[
//						'text' => __( "View Reports and Stats", 'wp-simple-firewall' ),
//						'href' => $modInsights->getUrl_SubInsightsPage( 'reports' ),
//					],
//				]
//			],

			'free_trial' => [
				'title'   => __( 'Free ShieldPRO Trial', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/emoji-smile.svg' ),
				'paras'   => [
					__( "Full, unrestricted access to ShieldPRO with no obligation.", 'wp-simple-firewall' ),
					__( "Turn-on the ShieldPRO trial within 60 seconds.", 'wp-simple-firewall' )
				],
				'actions' => [
					[
						'text' => __( "Get The Free Trial", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'free_trial' ),
					],
				],
				'classes' => $con->isPremiumActive() ? [] : [ 'highlighted', 'text-white', 'bg-primary' ],
				'hidden'  => $con->isPremiumActive()
			],

			'ips' => [
				'title'   => __( 'IP Blocking and Bypass', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/diagram-3.svg' ),
				'introjs' => __( "Protection begins by detecting bad bots - Review and Analyse all visitor IPs that have an impact on your site.", 'wp-simple-firewall' ),
				'paras'   => [
					__( "Shield automatically detects and blocks bad IP addresses based on your security settings.", 'wp-simple-firewall' ),
					__( "The IP Analysis Tool shows you all information for a given IP as it relates to your site.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "Analyse & Manage IPs", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'ips' ),
					],
					[
						'text' => __( "IP Blocking Settings", 'wp-simple-firewall' ),
						'href' => $con->getModule_IPs()->getUrl_AdminPage(),
					],
				]
			],

			'audit_trail' => [
				'title'   => __( 'Audit Trail', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/person-lines-fill.svg' ),
				'introjs' => __( "Track and review all important actions taken on your site - see the Who, What and When.", 'wp-simple-firewall' ),
				'paras'   => [
					__( "Provides in-depth logging for all major WordPress events.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "View Audit Log", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'audit' ),
					],
					[
						'text' => __( "Audit Trail Settings", 'wp-simple-firewall' ),
						'href' => $con->getModule_AuditTrail()->getUrl_AdminPage(),
					],
				]
			],

			'traffic' => [
				'title'   => __( 'Traffic Logging', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/stoplights.svg' ),
				'introjs' => __( "Monitor and watch traffic as it hits your site.", 'wp-simple-firewall' ),
				'paras'   => [
					__( "Use traffic logging to monitor visitor requests to your site.", 'wp-simple-firewall' ),
					__( "Traffic Rate Limiting lets you throttle requests from any single visitor.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "View Traffic Log", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'traffic' ),
					],
					[
						'text' => __( "Traffic Log Settings", 'wp-simple-firewall' ),
						'href' => $con->getModule_Traffic()->getUrl_AdminPage(),
					],
				]
			],

			'users' => [
				'title'   => __( 'WordPress Users', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/people.svg' ),
				'introjs' => __( "Set user session timeouts and passwords requirements.", 'wp-simple-firewall' ),
				'paras'   => [
					__( "Adds fine control over user sessions, account re-use, password strength and expiration, and user suspension.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "User Settings", 'wp-simple-firewall' ),
						'href' => $con->getModule_UserManagement()->getUrl_AdminPage(),
					],
					[
						'text' => __( "Manage User Sessions", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'users' ),
					],
				]
			],

			'comments' => [
				'title'   => __( 'Comment SPAM', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/chat-right-dots-fill.svg' ),
				'introjs' => __( "Block all Comment SPAM from bots and even detect SPAMMY human comments.", 'wp-simple-firewall' ),
				'paras'   => [
					__( "Shield blocks 100% of all automated comments by bots (the most common type of SPAM).", 'wp-simple-firewall' ).
					' '.__( "The Human SPAM filter will look for common spam words and content.", 'wp-simple-firewall' ),
					sprintf( '%s: %s',
						__( "Privacy Note", 'wp-simple-firewall' ),
						__( "Unlike Akismet, your comments and data are never sent off-site for analysis.", 'wp-simple-firewall' )
					)
				],
				'actions' => [
					[
						'text' => __( "Bot SPAM Settings", 'wp-simple-firewall' ),
						'href' => $modComments->getUrl_DirectLinkToSection( 'section_bot_comment_spam_protection_filter' ),
					],
					[
						'text' => __( "Human SPAM Settings", 'wp-simple-firewall' ),
						'href' => $modComments->getUrl_DirectLinkToSection( 'section_human_spam_filter' ),
					],
				]
			],

			'import' => [
				'title'   => __( 'Import/Export', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/arrow-down-up.svg' ),
				'paras'   => [
					__( "Use the import/export feature to quickly setup a new site based on the settings of another site.", 'wp-simple-firewall' ),
					__( "You can also setup automatic syncing of settings between sites.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "Run Import/Export", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'importexport' ),
					],
					[
						'text' => __( "Import/Export Settings", 'wp-simple-firewall' ),
						'href' => $modPlugin->getUrl_DirectLinkToSection( 'section_importexport' ),
					],
				]
			],

			'license' => [
				'title'   => __( 'Go PRO!', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/award.svg' ),
				'paras'   => [
					__( "By upgrading to ShieldPRO, you support ongoing Shield development and get access to exclusive PRO features.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => $con->isPremiumActive() ? __( "Manage PRO", 'wp-simple-firewall' ) : __( "Go PRO!", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'license' ),
					],
					[
						'text' => __( "See Exclusive ShieldPRO Features", 'wp-simple-firewall' ),
						'href' => 'https://shsec.io/gp',
						'new'  => true,
					],
				],
				'classes' => $con->isPremiumActive() ? [] : [ 'highlighted', 'text-white', 'bg-success' ]
			],

			'notes' => [
				'title'   => __( 'Admin Notes', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/pencil-square.svg' ),
				'introjs' => __( "Review and Analyse all visitor IPs that have an impact on your site.", 'wp-simple-firewall' ),
				'paras'   => [
					__( "Use these to keep note of important items or to-dos.", 'wp-simple-firewall' ),
					$latestNote
				],
				'actions' => [
					[
						'text' => __( "Manage Admin Notes", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'notes' ),
					],
				]
			],

			'whitelabel' => [
				'title'   => __( 'Whitelabel', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/sticky.svg' ),
				'paras'   => [
					__( "Re-brand the Shield Security plugin your image.", 'wp-simple-firewall' ),
					__( "Use this to enhance and solidify your brand with your clients and visitors.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "Manage White Label", 'wp-simple-firewall' ),
						'href' => $con->getModule_SecAdmin()
									  ->getUrl_DirectLinkToSection( 'section_whitelabel' ),
					],
				]
			],

			'integrations' => [
				'title'   => __( '3rd Party Integrations', 'wp-simple-firewall' ),
				'introjs' => __( "Integrate with your favourite plugins to block SPAM and manage Shield better.", 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/link-45deg.svg' ),
				'paras'   => [
					__( "Shield integrates with 3rd party plugins and services.", 'wp-simple-firewall' ),
					__( "Determine what integrations Shield should use and manage the settings for them.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "Manage Integrations", 'wp-simple-firewall' ),
						'href' => $con->getModule_Integrations()->getUrl_AdminPage(),
					],
				]
			],

			'docs' => [
				'title'   => __( 'Docs', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/book-half.svg' ),
				'paras'   => [
					sprintf( __( "Important information about %s releases and changes.", 'wp-simple-firewall' ), $name ),
				],
				'actions' => [
					[
						'text' => __( "View Docs", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'docs' ),
					],
				]
			],

			'debug' => [
				'title'   => __( 'Debug Info', 'wp-simple-firewall' ),
				'img'     => $con->urls->forImage( 'bootstrap/bug.svg' ),
				'paras'   => [
					__( "If you contact support, they may ask you to show them your Debug Information page.", 'wp-simple-firewall' ),
					__( "It's also an interesting place to see a summary of your WordPress configuration in 1 place.", 'wp-simple-firewall' ),
				],
				'actions' => [
					[
						'text' => __( "View Debug Info", 'wp-simple-firewall' ),
						'href' => $modInsights->getUrl_SubInsightsPage( 'debug' ),
					],
				]
			],

		];
	}

	/**
	 * Allows us to order Dashboard cards
	 */
	private function getAllCardSlugs() :array {
		return [
			'overview',
			'settings',
			'scans',
			'free_trial',
			'sec_admin',
			'reports',
			'ips',
			'audit_trail',
			'traffic',
			'users',
			'comments',
			'import',
			'license',
			'integrations',
			'notes',
			'whitelabel',
			'docs',
			'debug',
		];
	}
}