<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;

abstract class BaseRender extends BaseAction {

	use NonceVerifyNotRequired;

	public const TEMPLATE = '';

	protected function exec() {
		$this->render()->response();
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
		$template = $this->getRenderTemplate();
		if ( empty( $template ) ) {
			throw new ActionException( 'No template provided for render' );
		}

		try {
			$output = self::con()
				->comps
				->render
				->setTemplate( $template )
				->setData( $renderData )
				->setEnvironmentVars( $this->getTwigEnvironmentVars() )
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
		\ksort( $data );
		return \call_user_func_array(
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
		$con = self::con();
		$thisReq = $con->this_req;
		$urlBuilder = $con->urls;

		$ipStatus = new IpRuleStatus( $thisReq->ip );

		$isWhitelabelled = $con->comps->whitelabel->isEnabled();
		return [
			'unique_render_id' => PasswordGenerator::Gen( 6, false, true, false ),
			'nonce_field'      => wp_nonce_field( $con->getPluginPrefix(), '_wpnonce', true, false ),
			'classes'          => [
				'top_container' => \implode( ' ', \array_filter( [
					'odp-outercontainer',
					self::con()->isPremiumActive() ? 'is-pro' : 'is-not-pro',
					Services::Request()->query( Constants::NAV_ID, '' )
				] ) )
			],
			'flags'            => [
				'is_whitelabelled'  => $isWhitelabelled,
				'is_ip_whitelisted' => $ipStatus->isBypass(),
				'is_ip_blocked'     => $ipStatus->isBlocked(),
				'is_mode_live'      => $con->is_mode_live,
				'is_premium'        => $con->isPremiumActive(),
				'is_security_admin' => $con->isPluginAdmin(),
			],
			'head'             => [
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
						'content'   => \implode( ',', [ 'noindex', 'nofollow', 'noarchve', 'noimageindex' ] ),
					],
				],
				'scripts' => []
			],
			'hrefs'            => [
				'ajax' => $WP->ajaxURL(),

				'aar_forget_key' => $con->labels->url_secadmin_forgotten_key,
				'helpdesk'       => $con->labels->url_helpdesk,
				'plugin_home'    => $con->labels->PluginURI,
				'go_pro'         => 'https://clk.shldscrty.com/shieldgoprofeature',
				'goprofooter'    => 'https://clk.shldscrty.com/goprofooter',

				'dashboard_home' => $con->plugin_urls->adminHome(),
				'form_action'    => Services::Request()->getUri(),

				'facebook_group' => 'https://clk.shldscrty.com/pluginshieldsecuritygroupfb',
				'email_signup'   => 'https://clk.shldscrty.com/emailsubscribe',
			],
			'imgs'             => [
				'svgs'           => [
					'exit'        => $con->svgs->raw( 'box-arrow-left' ),
					'help'        => $con->svgs->raw( 'question-circle' ),
					'helpdesk'    => $con->svgs->raw( 'life-preserver' ),
					'newsletter'  => $con->svgs->raw( 'envelope' ),
					'ignore'      => $con->svgs->raw( 'eye-slash-fill' ),
					'info_square' => $con->svgs->raw( 'info-square' ),
					'megaphone'   => $con->svgs->raw( 'megaphone' ),
					'video'       => $con->svgs->raw( 'youtube' ),
					'search'      => $con->svgs->raw( 'search' ),
					'settings'    => $con->svgs->raw( 'gear' ),
					'menu'        => $con->svgs->raw( 'card-list' ),
					'email'       => $con->svgs->raw( 'envelope-fill' ),
					'triangle'    => $con->svgs->raw( 'triangle-fill' ),
					'expand'      => $con->svgs->raw( 'chevron-bar-expand' ),
					'home'        => $con->svgs->raw( 'house-door' ),
					'facebook'    => $con->svgs->raw( 'facebook' ),
					'twitter'     => $con->svgs->raw( 'twitter' ),
					'wordpress'   => $con->svgs->raw( 'wordpress' ),
					'blog'        => $con->svgs->raw( 'file-text-fill' ),
					'upgrade'     => $con->svgs->raw( 'coin' ),
				],
				'favicon'        => $urlBuilder->forImage( 'pluginlogo_24x24.png' ),
				'plugin_banner'  => $urlBuilder->forImage( 'banner-1500x500-transparent.png' ),
				'background_svg' => $urlBuilder->forImage( 'shield/background-blob' )
			],
			'content'          => [
				'options_form' => '',
				'alt'          => '',
				'actions'      => '',
				'help'         => '',
			],
			'strings'          => $this->getDisplayStrings(),
		];
	}

	private function getDisplayStrings() :array {
		$con = self::con();
		return [
			'btn_save'          => __( 'Save Options' ),
			'btn_options'       => __( 'Options' ),
			'btn_help'          => __( 'Help' ),
			'go_to_settings'    => __( 'Edit Settings', 'wp-simple-firewall' ),
			'on'                => __( 'On', 'wp-simple-firewall' ),
			'off'               => __( 'Off', 'wp-simple-firewall' ),
			'yes'               => __( 'Yes' ),
			'no'                => __( 'No' ),
			'never'             => __( 'Never', 'wp-simple-firewall' ),
			'time_until'        => __( 'Until', 'wp-simple-firewall' ),
			'time_since'        => __( 'Since', 'wp-simple-firewall' ),
			'more_info'         => __( 'Info', 'wp-simple-firewall' ),
			'view_details'      => __( 'View Details', 'wp-simple-firewall' ),
			'opt_info_helpdesk' => __( 'HelpDesk article', 'wp-simple-firewall' ),
			'opt_info_blog'     => __( 'Blog article', 'wp-simple-firewall' ),
			'opt_info_video'    => __( 'Watch the explainer video', 'wp-simple-firewall' ),
			'logged_in'         => __( 'Logged-In', 'wp-simple-firewall' ),
			'username'          => __( 'Username' ),
			'blog'              => __( 'Blog', 'wp-simple-firewall' ),
			'save_all_settings' => __( 'Save Settings', 'wp-simple-firewall' ),
			'plugin_name'       => $con->labels->Name,
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

			'mode' => __( 'Mode', 'wp-simple-firewall' ),

			'dashboard' => __( 'Dashboard', 'wp-simple-firewall' ),

			'are_you_sure'                 => __( 'Are you sure?', 'wp-simple-firewall' ),
			'description'                  => __( 'Description', 'wp-simple-firewall' ),
			'loading'                      => __( 'Loading', 'wp-simple-firewall' ),
			'aar_what_should_you_enter'    => __( 'Provide The Security Admin PIN.', 'wp-simple-firewall' ),
			'aar_to_manage_must_enter_key' => __( 'PIN Required', 'wp-simple-firewall' ),
			'aar_enter_access_key'         => __( 'Security Admin PIN', 'wp-simple-firewall' ),
			'aar_submit_access_key'        => __( 'Submit PIN', 'wp-simple-firewall' ),
			'aar_forget_key'               => __( 'Forgotten PIN', 'wp-simple-firewall' ),
			'show_help_video_section'      => __( 'Show help video for this section', 'wp-simple-firewall' ),

			'offense' => __( 'offense', 'wp-simple-firewall' ),
			'debug'   => __( 'Debug', 'wp-simple-firewall' ),

			'privacy_policy_agree'   => __( 'Agree To Privacy Policy', 'wp-simple-firewall' ),
			'privacy_policy_confirm' => __( "I confirm that I've read and I agree to the Privacy Policy", 'wp-simple-firewall' ),
			'privacy_policy_gdpr'    => __( 'We treat your information under our strict, and GDPR-compliant, privacy policy.', 'wp-simple-firewall' ),
			'privacy_policy'         => __( 'Privacy Policy', 'wp-simple-firewall' ),
			'privacy_never_spam'     => __( 'We never SPAM and you can remove yourself at any time.', 'wp-simple-firewall' ),

			'options'        => __( 'Options', 'wp-simple-firewall' ),
			'not_available'  => __( 'Sorry, please upgrade your plan to access this feature.', 'wp-simple-firewall' ),
			'not_enabled'    => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
			'please_upgrade' => __( 'You can get this security feature, and many more, by upgrading your ShieldPRO plan.', 'wp-simple-firewall' ),
			'please_enable'  => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
			'yyyymmdd'       => __( 'YYYY-MM-DD', 'wp-simple-firewall' ),

			'wphashes_token' => 'ShieldPRO API Token',

			'running_version' => sprintf( '%s %s', $con->labels->Name,
				Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ?
					sprintf( '<a href="%s" target="_blank" class="text-danger shield-footer-version">%s</a>',
						Services::WpGeneral()->getAdminUrl_Updates(), $con->cfg->version() ) : $con->cfg->version()
			),

			'product_name'    => __( 'Name', 'wp-simple-firewall' ),
			'license_active'  => __( 'Active', 'wp-simple-firewall' ),
			'license_status'  => __( 'Status', 'wp-simple-firewall' ),
			'license_key'     => __( 'Key', 'wp-simple-firewall' ),
			'license_expires' => __( 'Expires', 'wp-simple-firewall' ),
			'license_email'   => __( 'Owner', 'wp-simple-firewall' ),
			'last_checked'    => __( 'Checked', 'wp-simple-firewall' ),

			'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $con->labels->Name ),
			'recommendation'      => \ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
			'suggestion'          => \ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
			'no_security_notices' => __( 'There are no important security notices at this time.', 'wp-simple-firewall' ),
			'this_is_wonderful'   => __( 'This is wonderful!', 'wp-simple-firewall' ),

			'um_current_user_settings' => __( 'Current User Sessions', 'wp-simple-firewall' ),
			'um_username'              => __( 'Username', 'wp-simple-firewall' ),
			'um_logged_in_at'          => __( 'Logged In At', 'wp-simple-firewall' ),
			'um_last_activity_at'      => __( 'Last Activity At', 'wp-simple-firewall' ),
			'um_last_activity_uri'     => __( 'Last Activity URI', 'wp-simple-firewall' ),
			'um_login_ip'              => __( 'Login IP', 'wp-simple-firewall' ),

			'search_shield'            => sprintf( __( 'Search %s', 'wp-simple-firewall' ), $con->labels->Name ),
			'search_shield_label'      => __( 'Click to launch plugin search modal', 'wp-simple-firewall' ),
			'search_modal_placeholder' => __( 'Search using whole words of at least 3 characters.', 'wp-simple-firewall' ),
			'search_modal_you_could'   => __( 'You could search for', 'wp-simple-firewall' ),
			'search_suggestions'       => [
				sprintf( __( '%s options and modules', 'wp-simple-firewall' ), $con->labels->Name ),
				sprintf( __( '%s tools and features', 'wp-simple-firewall' ), $con->labels->Name ),
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