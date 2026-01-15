<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Services\Services;

class PageLicense extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_license';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/license.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$config = $con->cfg->configuration;
		$carb = Services::Request()->carbon();

		$lic = $con->comps->license->getLicense();

		$expiresAt = $lic->getExpiresAt();
		if ( $expiresAt > 0 && $expiresAt != \PHP_INT_MAX ) {
			// Expires At has a random addition added to disperse future license lookups
			// So we bring the license expiration back down to normal for user display.
			$endOfExpireDay = Services::Request()
									  ->carbon()
									  ->setTimestamp( $expiresAt )
									  ->startOfDay()->timestamp - 1;
			$expiresAtHuman = sprintf( '%s<br/><small>%s</small>',
				$carb->setTimestamp( $endOfExpireDay )->diffForHumans(),
				Services::WpGeneral()->getTimeStampForDisplay( $endOfExpireDay )
			);
		}
		else {
			$expiresAtHuman = 'n/a';
		}

		$lastReqAt = $lic->last_request_at;
		if ( empty( $lastReqAt ) ) {
			$checked = CommonDisplayStrings::get( 'never_label' );
		}
		else {
			$checked = sprintf( '%s<br/><small>%s</small>',
				$carb->setTimestamp( $lastReqAt )->diffForHumans(),
				Services::WpGeneral()->getTimeStampForDisplay( $lastReqAt )
			);
		}

		return [
			'flags'   => [
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
			],
			'hrefs'   => [
				'shield_pro_url' => 'https://clk.shldscrty.com/shieldpro',
				'iframe_url'     => $config->def( 'landing_page_url' ),
				'keyless_cp'     => $config->def( 'keyless_cp' ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'award' ),
				'svgs'                  => [
					'thumbs_up' => $con->svgs->raw( 'hand-thumbs-up.svg' ),
				],
			],
			'inputs'  => [
				'license_key' => [
					'name'      => $con->prefix( 'license_key', '_' ),
					'maxlength' => $config->def( 'license_key_length' ),
				]
			],
			'strings' => [
				'inner_page_title'    => sprintf( __( '%s License Management', 'wp-simple-firewall' ), $con->labels->Name ),
				'inner_page_subtitle' => sprintf( __( 'Seamlessly activate and manage your %s license without any license keys.', 'wp-simple-firewall' ), $con->labels->Name ),
				'pro_features'        => $this->getProFeatureStrings(),

				'pro_available_blurb'        => sprintf( __( '%s Pro is available from our online store.', 'wp-simple-firewall' ), $con->labels->Name ),
				'title_license_summary'    => __( 'License Summary', 'wp-simple-firewall' ),
				'title_license_activation' => __( 'License Activation', 'wp-simple-firewall' ),
				'license_step_purchase_prefix' => __( 'Just grab a new license from the', 'wp-simple-firewall' ),
				'license_step_purchase_link'   => sprintf( __( '%s Pro store', 'wp-simple-firewall' ), $con->labels->Name ),
				'license_step_register'        => __( 'Register your site URL with our control panel.', 'wp-simple-firewall' ),
				'license_step_activate'        => __( "Activate your license on your sites using the 'Check License' button.", 'wp-simple-firewall' ),
				'check_license'            => __( 'Check License', 'wp-simple-firewall' ),
				'clear_license'            => __( 'Remove License', 'wp-simple-firewall' ),
				'url_to_activate'          => __( 'URL To Activate', 'wp-simple-firewall' ),
				'activate_site_in'         => sprintf(
					__( 'Activate this site URL in your %s control panel', 'wp-simple-firewall' ),
					__( 'Keyless Activation', 'wp-simple-firewall' )
				),
				'license_check_limit'      => sprintf( __( 'Licenses may be checked once every %s seconds', 'wp-simple-firewall' ), 20 ),
				'more_frequent'            => __( 'more frequent checks will be ignored', 'wp-simple-firewall' ),
				'incase_debug'             => __( 'In case of activation problems, click the link', 'wp-simple-firewall' ),
				'cta_upgrade'              => sprintf( __( 'Upgrade To %s Pro Now', 'wp-simple-firewall' ), $con->labels->Name ),
				'cta_view_features'        => __( 'See All PRO Features and Extras', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'license_table'  => [
					'product_name'    => $lic->item_name,
					'license_active'  => $con->comps->license->hasValidWorkingLicense() ? '&#10004;' : '&#10006;',
					'license_expires' => $expiresAtHuman,
					'license_email'   => $lic->customer_email,
					'last_checked'    => $checked,
					'wphashes_token'  => $con->comps->api_token->hasToken() ? '&#10004;' : '&#10006;',
					'installation_id' => ( new InstallationID() )->id(),
				],
				'activation_url' => $con->comps->license->activationURL(),
			],
		];
	}

	private function getProFeatureStrings() :array {
		$con = self::con();
		return [
			[
				'title' => sprintf( __( 'Protect your %s files', 'wp-simple-firewall' ), '<code>wp-config.php, .htaccess</code>' ),
				'lines' => [
					sprintf( __( 'The only WordPress security plugin to add monitoring of your %s files with automatic rollback and recovery.', 'wp-simple-firewall' ), '<code>wp-config.php</code>' ),
				],
				'href'  => 'https://clk.shldscrty.com/ki'
			],
			[
				'title' => __( 'Support for WooCommerce, Contact Form 7, Elementor PRO, Ninja Form & more', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Provide tighter security for your WooCommerce customers and protect against Contact Form SPAM.', 'wp-simple-firewall' ),
					__( 'Includes protection for: ', 'wp-simple-firewall' ).\implode( ', ', $this->getAllIntegrationNames() )
				],
			],
			[
				'title' => sprintf( '%s: %s', __( 'Malware Scanner', 'wp-simple-firewall' ), __( 'Auto-learning and Detects Never-Before-Seen Malware', 'wp-simple-firewall' ) ),
				'lines' => [
					__( 'Detects common and uncommon malware patterns in PHP files and alerts you immediately.', 'wp-simple-firewall' ),
					sprintf( __( 'With %s crowd-sourcing intelligence, noisy false-positives that waste your time are automatically hidden so you can focus on risks that matter.', 'wp-simple-firewall' ), $con->labels->Name ),
				],
				'href'  => 'https://clk.shldscrty.com/kj'
			],
			[
				'title' => __( 'Plugin and Theme Vulnerability Scanner', 'wp-simple-firewall' ),
				'lines' => [
					sprintf( __( 'Alerts to plugin/theme vulnerabilities. %s can automatically deploy upgrade they become available.', 'wp-simple-firewall' ), $con->labels->Name ),
				],
				'href'  => 'https://clk.shldscrty.com/kk'
			],
			[
				'title' => __( 'Catch Plugin & Theme Hacks Immediately', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Be alerted to ANY unauthorized changes to plugins/themes.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://clk.shldscrty.com/kl'
			],
			[
				'title' => __( 'Traffic Rate Limiting', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Prevent abuse of your web hosting resources by detecting and blocking bots that send too many requests to your site.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://clk.shldscrty.com/km'
			],
			[
				'title' => sprintf( '%s: %s', __( 'Intelligence From The Collective', 'wp-simple-firewall' ), $con->labels->getBrandName( 'shieldnet' ) ),
				'lines' => [
					sprintf( __( 'Take advantage of the intelligence gathered throughout the entire %s network to better protect your WordPress sites', 'wp-simple-firewall' ), $con->labels->Name ),
				],
				'href'  => 'https://clk.shldscrty.com/kn'
			],
			[
				'title' => __( 'Easiest, Frustration-Free WP Pro-Upgrade Anywhere', 'wp-simple-firewall' ),
				'lines' => [
					sprintf( __( 'No more license keys to remember/copy-paste! Simply activate your site URL in your %s control panel and get Pro features enabled on your site automatically.', 'wp-simple-firewall' ), $con->labels->Name ),
				],
				'href'  => 'https://clk.shldscrty.com/ko'
			],
			[
				'title' => __( 'MainWP Integration', 'wp-simple-firewall' ).' ('.__( 'No extra extension plugins required', 'wp-simple-firewall' ).')',
				'lines' => [
					__( 'Use MainWP to manage and monitor the security of all your WordPress sites.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://clk.shldscrty.com/kp'
			],
			[
				'title' => __( 'Powerful User Password Policies', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Ensures that all users maintain strong passwords.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://clk.shldscrty.com/kq'
			],
			[
				'title' => __( 'White Label', 'wp-simple-firewall' ),
				'lines' => [
					sprintf( __( 'Re-Brand %s as your own!', 'wp-simple-firewall' ), $con->labels->Name ),
				],
				'href'  => 'https://clk.shldscrty.com/kr'
			],
			[
				'title' => __( 'Exclusive Customer Support', 'wp-simple-firewall' ),
				'lines' => [
					sprintf( __( 'Technical support for %s is exclusive to Pro customers.', 'wp-simple-firewall' ), $con->labels->Name ),
				],
			],
			[
				'title' => __( 'Unlimited Activity Log', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Retain logs for as long as you need without limits.', 'wp-simple-firewall' ),
				],
			],
		];
	}

	private function getAllIntegrationNames() :array {
		return \array_map(
			function ( $provider ) {
				return ( new $provider() )->getHandlerName();
			},
			\array_merge(
				self::con()->comps->forms_users->enumProviders(),
				self::con()->comps->forms_spam->enumProviders()
			)
		);
	}
}
