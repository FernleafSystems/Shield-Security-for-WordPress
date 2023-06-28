<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminLogin;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

abstract class BaseRender extends BaseAction {

	use NonceVerifyNotRequired;

	public const TEMPLATE = '';

	protected function exec() {
		return $this->render()->response();
	}

	/**
	 * @throws ActionException
	 */
	private function render() :self {
		$response = $this->response();
		$respData = $response->action_response_data;
		$respData[ 'render_template' ] = $this->getRenderTemplate();
		$respData[ 'render_data' ] = $this->buildRenderData();
		$respData[ 'render_output' ] = $this->buildRenderOutput( $respData[ 'render_data' ] );

		$respData[ 'html' ] = $respData[ 'render_output' ]; // TODO: This is a hack to get the data into the AJAX response

		$response->success = $respData[ 'success' ] ?? true;
		$response->action_response_data = $respData;
		return $this;
	}

	/**
	 * @throws ActionException
	 */
	protected function buildRenderOutput( array $renderData = [] ) :string {
		$con = $this->con();
		$template = $this->getRenderTemplate();
		if ( empty( $template ) ) {
			throw new ActionException( 'No template provided for render' );
		}

		try {
			$renderer = $con->getRenderer();

			$ext = Paths::Ext( $template );
			if ( empty( $ext ) || strtolower( $ext ) === 'twig' ) {
				$renderer->setTemplateEngineTwig();
			}
			else {
				$renderer->setTemplateEnginePhp();
			}

			$output = $renderer->setTemplate( $template )
							   ->setRenderVars( $renderData )
							   ->setTwigEnvironmentVars( $this->getTwigEnvironmentVars() )
							   ->render();
		}
		catch ( \Exception $e ) {
			$output = sprintf( 'Exception during render for %s: "%s"', static::SLUG, $e->getMessage() );
		}
		return $output;
	}

	/**
	 * @throws ActionException
	 */
	protected function buildRenderData() :array {
		$data = $this->getAllRenderDataArrays();
		ksort( $data );
		return call_user_func_array(
			[ Services::DataManipulation(), 'mergeArraysRecursive' ],
			$data
		);
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		return [
			0  => $this->getCommonDisplayData(),
			10 => $this->action_data,
			50 => $this->getRenderData(),
		];
	}

	/**
	 * @throws ActionException
	 */
	protected function getRenderData() :array {
		return [];
	}

	/**
	 * @throws ActionException
	 */
	protected function getRenderTemplate() :string {
		$t = static::TEMPLATE;
		if ( empty( $t ) ) {
			$t = $this->action_data[ 'render_action_template' ];
		}
		if ( empty( $t ) ) {
			throw new ActionException( sprintf( 'Render action %s has no render template provided', static::class ) );
		}
		return $t;
	}

	public function getCommonDisplayData() :array {
		$WP = Services::WpGeneral();
		$con = $this->con();
		$thisReq = $con->this_req;
		$urlBuilder = $con->urls;

		/** @var Options $pluginOptions */
		$pluginOptions = $con->getModule_Plugin()->getOptions();

		$ipStatus = new IpRuleStatus( $thisReq->ip );

		$isWhitelabelled = $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled();
		return [
			'unique_render_id' => uniqid(),
			'nonce_field'      => wp_nonce_field( $con->getPluginPrefix(), '_wpnonce', true, false ),

			'ajax'    => [
				'sec_admin_login' => ActionData::Build( SecurityAdminLogin::class ),
			],
			'classes' => [
				'top_container' => implode( ' ', array_filter( [
					'odp-outercontainer',
					$this->con()->isPremiumActive() ? 'is-pro' : 'is-not-pro',
					Services::Request()->query( Constants::NAV_ID, '' )
				] ) )
			],
			'flags'   => [
				'has_session'             => $con->getModule_Plugin()->getSessionCon()->current()->valid,
				'display_helpdesk_widget' => !$isWhitelabelled,
				'is_whitelabelled'        => $isWhitelabelled,
				'is_ip_whitelisted'       => $ipStatus->isBypass(),
				'is_ip_blocked'           => $ipStatus->isBlocked(),
				'is_mode_live'            => $con->is_mode_live,
				'is_premium'              => $con->isPremiumActive(),
				'show_transfer_switch'    => $con->isPremiumActive(),
				'is_wpcli'                => $pluginOptions->isEnabledWpcli(),
				'is_security_admin'       => $con->isPluginAdmin(),
			],
			'head'    => [
				'html'    => [
					'lang' => $WP->getLocale( '-' ),
					'dir'  => is_rtl() ? 'rtl' : 'ltr',
				],
				'meta'    => [
					[
						'type'      => 'http-equiv',
						'type_type' => 'Cache-Control',
						'content'   => 'no-store, no-cache',
					],
					[
						'type'      => 'http-equiv',
						'type_type' => 'Cache-Control',
						'content'   => 'max-age=0',
					],
					[
						'type'      => 'http-equiv',
						'type_type' => 'Expires',
						'content'   => '0',
					],
					[
						'type'      => 'name',
						'type_type' => 'robots',
						'content'   => implode( ',', [ 'noindex', 'nofollow', 'noarchve', 'noimageindex' ] ),
					],
				],
				'scripts' => []
			],
			'hrefs'   => [
				'ajax' => $WP->ajaxURL(),

				'aar_forget_key' => $con->labels->url_secadmin_forgotten_key,
				'helpdesk'       => $con->labels->url_helpdesk,
				'plugin_home'    => $con->labels->PluginURI,
				'go_pro'         => 'https://shsec.io/shieldgoprofeature',
				'goprofooter'    => 'https://shsec.io/goprofooter',

				'dashboard_home' => $con->plugin_urls->adminHome(),
				'form_action'    => Services::Request()->getUri(),

				'facebook_group' => 'https://shsec.io/pluginshieldsecuritygroupfb',
			],
			'imgs'    => [
				'svgs'           => [
					'exit'        => $con->svgs->raw( 'box-arrow-left.svg' ),
					'help'        => $con->svgs->raw( 'question-circle.svg' ),
					'helpdesk'    => $con->svgs->raw( 'life-preserver.svg' ),
					'ignore'      => $con->svgs->raw( 'eye-slash-fill.svg' ),
					'info_square' => $con->svgs->raw( 'info-square.svg' ),
					'megaphone'   => $con->svgs->raw( 'megaphone.svg' ),
					'video'       => $con->svgs->raw( 'youtube.svg' ),
					'search'      => $con->svgs->raw( 'search.svg' ),
					'settings'    => $con->svgs->raw( 'gear.svg' ),
					'email'       => $con->svgs->raw( 'envelope-fill.svg' ),
					'triangle'    => $con->svgs->raw( 'triangle-fill.svg' ),
					'home'        => $con->svgs->raw( 'house-door.svg' ),
					'facebook'    => $con->svgs->raw( 'facebook.svg' ),
					'twitter'     => $con->svgs->raw( 'twitter.svg' ),
					'wordpress'   => $con->svgs->raw( 'wordpress.svg' ),
				],
				'favicon'        => $urlBuilder->forImage( 'pluginlogo_24x24.png' ),
				'plugin_banner'  => $urlBuilder->forImage( 'banner-1500x500-transparent.png' ),
				'background_svg' => $urlBuilder->forImage( 'shield/background-blob.svg' )
			],
			'content' => [
				'options_form' => '',
				'alt'          => '',
				'actions'      => '',
				'help'         => '',
			],
			'strings' => $this->getDisplayStrings(),
		];
	}

	private function getDisplayStrings() :array {
		$WP = Services::WpGeneral();
		$con = $this->con();
		$name = $con->getHumanName();

		$proFeatures = [
			__( 'More Scans', 'wp-simple-firewall' ),
			__( 'Malware Scanner', 'wp-simple-firewall' ),
			__( 'Scan Every Hour', 'wp-simple-firewall' ),
			__( 'White Label', 'wp-simple-firewall' ),
			__( 'Import/Export', 'wp-simple-firewall' ),
			__( 'Better Bot Detection', 'wp-simple-firewall' ),
			__( 'Password Policies', 'wp-simple-firewall' ),
			__( 'WooCommerce Support', 'wp-simple-firewall' ),
			__( 'MainWP Integration', 'wp-simple-firewall' ),
		];
		shuffle( $proFeatures );
		$proFeaturesDisplay = array_slice( $proFeatures, 0, 6 );
		$proFeaturesDisplay[] = __( 'and much more!' );

		$isAdvanced = $this->con()->getModule_Plugin()->isShowAdvanced();

		return [
			'btn_save'          => __( 'Save Options' ),
			'btn_options'       => __( 'Options' ),
			'btn_help'          => __( 'Help' ),
			'go_to_settings'    => __( 'Configuration', 'wp-simple-firewall' ),
			'on'                => __( 'On', 'wp-simple-firewall' ),
			'off'               => __( 'Off', 'wp-simple-firewall' ),
			'yes'               => __( 'Yes' ),
			'no'                => __( 'No' ),
			'never'             => __( 'Never', 'wp-simple-firewall' ),
			'time_until'        => __( 'Until', 'wp-simple-firewall' ),
			'time_since'        => __( 'Since', 'wp-simple-firewall' ),
			'more_info'         => __( 'Info', 'wp-simple-firewall' ),
			'view_details'      => __( 'View Details', 'wp-simple-firewall' ),
			'opt_info_helpdesk' => __( 'Read the HelpDesk article for this option', 'wp-simple-firewall' ),
			'opt_info_blog'     => __( 'Read our Blog article for this option', 'wp-simple-firewall' ),
			'logged_in'         => __( 'Logged-In', 'wp-simple-firewall' ),
			'username'          => __( 'Username' ),
			'blog'              => __( 'Blog', 'wp-simple-firewall' ),
			'save_all_settings' => __( 'Save Settings', 'wp-simple-firewall' ),
			'plugin_name'       => $con->getHumanName(),
			'options_title'     => __( 'Options', 'wp-simple-firewall' ),
			'options_summary'   => __( 'Configure Module', 'wp-simple-firewall' ),
			'actions_title'     => __( 'Actions and Info', 'wp-simple-firewall' ),
			'actions_summary'   => __( 'Perform actions for this module', 'wp-simple-firewall' ),
			'help_title'        => __( 'Help', 'wp-simple-firewall' ),
			'help_summary'      => __( 'Learn More', 'wp-simple-firewall' ),
			'installation_id'   => __( 'Installation ID', 'wp-simple-firewall' ),
			'ip_address'        => __( 'IP Address', 'wp-simple-firewall' ),
			'select'            => __( 'Select' ),
			'filters_clear'     => __( 'Clear Filters', 'wp-simple-firewall' ),
			'filters_apply'     => __( 'Apply Filters', 'wp-simple-firewall' ),
			'jump_to_module'    => __( 'Jump To Module Settings', 'wp-simple-firewall' ),
			'this_page'         => __( 'This Page', 'wp-simple-firewall' ),
			'jump_to_option'    => __( 'Find Plugin Option', 'wp-simple-firewall' ),
			'type_below_search' => __( 'Type below to search all plugin options', 'wp-simple-firewall' ),
			'pro_only_option'   => __( 'Upgrade Required', 'wp-simple-firewall' ),
			'go_pro'            => __( 'Go Pro!', 'wp-simple-firewall' ),

			'mode'            => __( 'Mode', 'wp-simple-firewall' ),
			'mode_simple'     => __( 'Simple', 'wp-simple-firewall' ),
			'mode_advanced'   => __( 'Advanced', 'wp-simple-firewall' ),
			'mode_switchto'   => sprintf( '%s: %s', __( 'Switch To', 'wp-simple-firewall' ),
				$isAdvanced ? __( 'Simple', 'wp-simple-firewall' ) : __( 'Advanced', 'wp-simple-firewall' ) ),
			'mode_switchfrom' => sprintf( '%s: %s', __( 'Mode', 'wp-simple-firewall' ),
				$isAdvanced ? __( 'Advanced', 'wp-simple-firewall' ) : __( 'Simple', 'wp-simple-firewall' ) ),

			'dashboard'        => __( 'Dashboard', 'wp-simple-firewall' ),
			'dashboard_shield' => sprintf( __( '%s Dashboard', 'wp-simple-firewall' ), $con->getHumanName() ),

			'are_you_sure'                 => __( 'Are you sure?', 'wp-simple-firewall' ),
			'description'                  => __( 'Description', 'wp-simple-firewall' ),
			'loading'                      => __( 'Loading', 'wp-simple-firewall' ),
			'aar_what_should_you_enter'    => __( 'This security plugin is restricted to administrators with the Security Admin PIN.', 'wp-simple-firewall' ),
			'aar_to_manage_must_enter_key' => __( 'To manage this plugin you must enter the Security Admin PIN.', 'wp-simple-firewall' ),
			'aar_enter_access_key'         => __( 'Security Admin PIN', 'wp-simple-firewall' ),
			'aar_submit_access_key'        => __( 'Submit Security Admin PIN', 'wp-simple-firewall' ),
			'aar_forget_key'               => __( "Forgotten PIN", 'wp-simple-firewall' ),
			'supply_password'              => __( 'Supply Password', 'wp-simple-firewall' ),
			'confirm_password'             => __( 'Confirm Password', 'wp-simple-firewall' ),
			'show_help_video_section'      => __( 'Show help video for this section', 'wp-simple-firewall' ),

			'offense' => __( 'offense', 'wp-simple-firewall' ),
			'debug'   => __( 'Debug', 'wp-simple-firewall' ),

			'privacy_policy_agree'   => __( 'Agree To Privacy Policy', 'wp-simple-firewall' ),
			'privacy_policy_confirm' => __( "I confirm that I've read and I agree to the Privacy Policy", 'wp-simple-firewall' ),
			'privacy_policy_gdpr'    => __( 'We treat your information under our strict, and GDPR-compliant, privacy policy.', 'wp-simple-firewall' ),
			'privacy_policy'         => __( 'Privacy Policy', 'wp-simple-firewall' ),
			'privacy_never_spam'     => __( 'We never SPAM and you can remove yourself at any time.', 'wp-simple-firewall' ),

			'pro_features'       => __( 'Pro features include', 'wp-simple-firewall' ),
			'join_thousands_H'   => __( "Join The 1,000s Who've Already Upgraded Their WordPress Security To Better Protect Their Sites.", 'wp-simple-firewall' ),
			'join_thousands_P'   => implode( ', ', $proFeaturesDisplay ),
			'get_pro_protection' => __( 'Get Pro Protection', 'wp-simple-firewall' ),

			'options'        => __( 'Options', 'wp-simple-firewall' ),
			'not_available'  => __( 'Sorry, this feature is included with Pro subscriptions.', 'wp-simple-firewall' ),
			'not_enabled'    => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
			'please_upgrade' => __( 'You can get this feature (along with loads more) by going Pro.', 'wp-simple-firewall' ),
			'please_enable'  => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
			'yyyymmdd'       => __( 'YYYY-MM-DD', 'wp-simple-firewall' ),

			'wphashes_token'      => 'ShieldPRO API Token',
			'is_opt_importexport' => __( 'Is this option included with import/export?', 'wp-simple-firewall' ),

			'search_select'   => [
				'title' => ucwords( __( 'Search for a plugin option', 'wp-simple-firewall' ) ),
			],
			'running_version' => sprintf( '%s %s', $con->getHumanName(),
				Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ?
					sprintf( '<a href="%s" target="_blank" class="text-danger shield-footer-version">%s</a>',
						$WP->getAdminUrl_Updates(), $con->getVersion() )
					: $con->getVersion()
			),

			'title_license_summary'    => __( 'License Summary', 'wp-simple-firewall' ),
			'title_license_activation' => __( 'License Activation', 'wp-simple-firewall' ),
			'check_availability'       => __( 'Check License Availability For This Site', 'wp-simple-firewall' ),
			'check_license'            => __( 'Check License', 'wp-simple-firewall' ),
			'clear_license'            => __( 'Clear License Status', 'wp-simple-firewall' ),
			'url_to_activate'          => __( 'URL To Activate', 'wp-simple-firewall' ),
			'activate_site_in'         => sprintf(
				__( 'Activate this site URL in your %s control panel', 'wp-simple-firewall' ),
				__( 'Keyless Activation', 'wp-simple-firewall' )
			),
			'license_check_limit'      => sprintf( __( 'Licenses may be checked once every %s seconds', 'wp-simple-firewall' ), 20 ),
			'more_frequent'            => __( 'more frequent checks will be ignored', 'wp-simple-firewall' ),
			'incase_debug'             => __( 'In case of activation problems, click the link', 'wp-simple-firewall' ),

			'product_name'    => __( 'Name', 'wp-simple-firewall' ),
			'license_active'  => __( 'Active', 'wp-simple-firewall' ),
			'license_status'  => __( 'Status', 'wp-simple-firewall' ),
			'license_key'     => __( 'Key', 'wp-simple-firewall' ),
			'license_expires' => __( 'Expires', 'wp-simple-firewall' ),
			'license_email'   => __( 'Owner', 'wp-simple-firewall' ),
			'last_checked'    => __( 'Checked', 'wp-simple-firewall' ),
			'last_errors'     => __( 'Error', 'wp-simple-firewall' ),

			'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $name ),
			'recommendation'      => ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
			'suggestion'          => ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
			'no_security_notices' => __( 'There are no important security notices at this time.', 'wp-simple-firewall' ),
			'this_is_wonderful'   => __( 'This is wonderful!', 'wp-simple-firewall' ),

			'um_current_user_settings' => __( 'Current User Sessions', 'wp-simple-firewall' ),
			'um_username'              => __( 'Username', 'wp-simple-firewall' ),
			'um_logged_in_at'          => __( 'Logged In At', 'wp-simple-firewall' ),
			'um_last_activity_at'      => __( 'Last Activity At', 'wp-simple-firewall' ),
			'um_last_activity_uri'     => __( 'Last Activity URI', 'wp-simple-firewall' ),
			'um_login_ip'              => __( 'Login IP', 'wp-simple-firewall' ),

			'search_shield'            => sprintf( __( 'Search %s', 'wp-simple-firewall' ), $con->getHumanName() ),
			'search_modal_placeholder' => __( 'Search using whole words of at least 3 characters.', 'wp-simple-firewall' ),
			'search_modal_you_could'   => __( 'You could search for', 'wp-simple-firewall' ),
			'search_suggestions'       => [
				sprintf( __( '%s options and modules', 'wp-simple-firewall' ), $con->getHumanName() ),
				sprintf( __( '%s tools and features', 'wp-simple-firewall' ), $con->getHumanName() ),
				__( 'IP addresses', 'wp-simple-firewall' ),
				__( 'Help docs & resources', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getTwigEnvironmentVars() :array {
		return [
			'strict_variables' => false,
		];
	}
}