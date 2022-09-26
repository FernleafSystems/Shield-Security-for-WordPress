<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\{
	LicenseAction,
	LicenseCheckDebug
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class PageLicense extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_license';
	const PRIMARY_MOD = 'license';
	const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/license.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$opts = $mod->getOptions();
		$WP = Services::WpGeneral();
		$carb = Services::Request()->carbon();

		$lic = $mod->getLicenseHandler()->getLicense();

		$expiresAt = $lic->getExpiresAt();
		if ( $expiresAt > 0 && $expiresAt != PHP_INT_MAX ) {
			// Expires At has a random addition added to disperse future license lookups
			// So we bring the license expiration back down to normal for user display.
			$endOfExpireDay = Services::Request()
									  ->carbon()
									  ->setTimestamp( $expiresAt )
									  ->startOfDay()->timestamp - 1;
			$expiresAtHuman = sprintf( '%s<br/><small>%s</small>',
				$carb->setTimestamp( $endOfExpireDay )->diffForHumans(),
				$WP->getTimeStampForDisplay( $endOfExpireDay )
			);
		}
		else {
			$expiresAtHuman = 'n/a';
		}

		$lastReqAt = $lic->last_request_at;
		if ( empty( $lastReqAt ) ) {
			$checked = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$checked = sprintf( '%s<br/><small>%s</small>',
				$carb->setTimestamp( $lastReqAt )->diffForHumans(),
				$WP->getTimeStampForDisplay( $lastReqAt )
			);
		}

		$strings = $mod->getStrings()->getDisplayStrings();
		$strings[ 'pro_features' ] = $this->getProFeatureStrings();
		return [
			'vars'    => [
				'license_table'  => [
					'product_name'    => $opts->getDef( $lic->is_central ? 'license_item_name_sc' : 'license_item_name' ),
					'license_active'  => $mod->getLicenseHandler()->hasValidWorkingLicense() ? '&#10004;' : '&#10006;',
					'license_expires' => $expiresAtHuman,
					'license_email'   => $lic->customer_email,
					'last_checked'    => $checked,
					'last_errors'     => $mod->hasLastErrors() ? $mod->getLastErrors( true ) : '',
					'wphashes_token'  => $mod->getWpHashesTokenManager()->hasToken() ? '&#10004;' : '&#10006;',
					'installation_id' => $con->getInstallationID()[ 'id' ],
				],
				'activation_url' => $WP->getHomeUrl(),
				'error'          => $mod->getLastErrors( true ),
			],
			'inputs'  => [
				'license_key' => [
					'name'      => $con->prefixOption( 'license_key' ),
					'maxlength' => $opts->getDef( 'license_key_length' ),
				]
			],
			'ajax'    => [
				'license_action'   => ActionData::Build( LicenseAction::SLUG ),
				'connection_debug' => ActionData::Build( LicenseCheckDebug::SLUG )
			],
			'hrefs'   => [
				'shield_pro_url' => 'https://shsec.io/shieldpro',
				'iframe_url'     => $opts->getDef( 'landing_page_url' ),
				'keyless_cp'     => $opts->getDef( 'keyless_cp' ),
			],
			'imgs'    => [
				'svgs' => [
					'thumbs_up' => $con->svgs->raw( 'bootstrap/hand-thumbs-up.svg' )
				],
			],
			'flags'   => [
				'show_ads'              => false,
				'button_enabled_check'  => true,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
				'has_error'             => $mod->hasLastErrors()
			],
			'strings' => $strings,
		];
	}

	private function getProFeatureStrings() :array {
		return [
			[
				'title' => sprintf( __( 'Protect your %s files', 'wp-simple-firewall' ), '<code>wp-config.php, .htaccess</code>' ),
				'lines' => [
					sprintf( __( 'The only WordPress security plugin to add monitoring of your %s files with automatic rollback and recovery.', 'wp-simple-firewall' ), '<code>wp-config.php</code>' ),
				],
				'href'  => 'https://shsec.io/ki'
			],
			[
				'title' => __( 'Support for WooCommerce, Contact Form 7, Elementor PRO, Ninja Form & more', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Provide tighter security for your WooCommerce customers and protect against Contact Form SPAM.', 'wp-simple-firewall' ),
					__( 'Includes protection for: ', 'wp-simple-firewall' ).implode( ', ', $this->getAllIntegrationNames() )
				],
			],
			[
				'title' => sprintf( '%s: %s', __( 'Malware Scanner', 'wp-simple-firewall' ), __( 'Auto-learning and Detects Never-Before-Seen Malware', 'wp-simple-firewall' ) ),
				'lines' => [
					__( 'Detects common and uncommon malware patterns in PHP files and alerts you immediately.', 'wp-simple-firewall' ),
					__( 'With ShieldNET crowd-sourcing intelligence, Shield automatically hides false-positives so you can focus on risks that matter, and can ignore the noise that wastes your time.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kj'
			],
			[
				'title' => __( 'Plugin and Theme Vulnerability Scanner', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Alerts to plugin/theme vulnerabilities. Shield can then automatically upgrade as updates become available.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kk'
			],
			[
				'title' => __( 'Catch Plugin & Theme Hacks Immediately', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Be alerted to ANY unauthorized changes to plugins/themes.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kl'
			],
			[
				'title' => __( 'Traffic Rate Limiting', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Prevent abuse of your web hosting resources by detecting and blocking bots that send too many requests to your site.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/km'
			],
			[
				'title' => sprintf( '%s: %s', __( 'Intelligence From The Collective', 'wp-simple-firewall' ), 'ShieldNET' ),
				'lines' => [
					__( 'Take advantage of the intelligence gathered throughout the entire Shield network to better protect your WordPress sites', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kn'
			],
			[
				'title' => __( 'Easiest, Frustration-Free WP Pro-Upgrade Anywhere', 'wp-simple-firewall' ),
				'lines' => [
					__( 'No more license keys to remember/copy-paste! Simply activate your site URL in your ShieldPRO control panel and get Pro features enabled on your site automatically.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/ko'
			],
			[
				'title' => __( 'MainWP Integration', 'wp-simple-firewall' ).' ('.__( 'No extra extension plugins required', 'wp-simple-firewall' ).')',
				'lines' => [
					__( 'Use MainWP to manage and monitor the security of all your WordPress sites.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kp'
			],
			[
				'title' => __( 'Powerful User Password Policies', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Ensures that all users maintain strong passwords.', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kq'
			],
			[
				'title' => __( 'White Label', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Re-Brand Shield Security as your own!', 'wp-simple-firewall' ),
				],
				'href'  => 'https://shsec.io/kr'
			],
			[
				'title' => __( 'Exclusive Customer Support', 'wp-simple-firewall' ),
				'lines' => [
					__( 'Technical support for Shield is exclusive to Pro customers.', 'wp-simple-firewall' ),
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
		$modIntegrations = $this->getCon()->getModule_Integrations();
		return array_map(
			function ( $providerClass ) use ( $modIntegrations ) {
				/** @var BaseHandler $provider */
				$provider = ( new $providerClass() )->setMod( $modIntegrations );
				return $provider->getHandlerName();
			},
			array_merge(
				$modIntegrations->getController_UserForms()->enumProviders(),
				$modIntegrations->getController_SpamForms()->enumProviders()
			)
		);
	}
}