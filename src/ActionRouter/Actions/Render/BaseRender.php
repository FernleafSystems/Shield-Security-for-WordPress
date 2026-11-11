<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;

abstract class BaseRender extends BaseAction {

	public const TEMPLATE = '';
	public const GO_PRO_URL = 'https://clk.shldscrty.com/shieldgoprofeature';
	public const COMPARE_FEATURES_URL = 'https://clk.shldscrty.com/gp';
	public const RENDER_ERROR_EXCEPTION = 'render_exception';

	private string $renderErrorCode = '';

	protected function exec() {
		$this->render()->response();
	}

	/**
	 * @throws ActionException
	 */
	private function render() :self {
		$response = $this->response();
		$respData = $response->payload();
		$respData[ 'render_template' ] = $this->getRenderTemplate();
		$respData[ 'render_data' ] = $this->buildRenderData();
		$respData[ 'render_output' ] = $this->buildRenderOutput( $respData[ 'render_data' ] );
		$respData[ 'render_error' ] = $this->renderErrorCode !== '';
		$respData[ 'render_error_code' ] = $this->renderErrorCode;

		// Existing AJAX render consumers still read html; render_output is the canonical render payload.
		$respData[ 'html' ] = $respData[ 'render_output' ];
		$payloadSuccess = (bool)( $respData[ 'success' ] ?? true );
		unset( $respData[ 'success' ] );

		$response
			->setPayload( $respData )
			->setPayloadSuccess( $payloadSuccess );
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

		$this->renderErrorCode = '';
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
			$this->renderErrorCode = self::RENDER_ERROR_EXCEPTION;
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

				'aar_forget_key'   => $con->labels->url_secadmin_forgotten_key,
				'plugin_home'      => $con->labels->PluginURI,
				'go_pro'           => self::GO_PRO_URL,
				'compare_features' => self::COMPARE_FEATURES_URL,

				'dashboard_home' => $con->plugin_urls->adminHome(),
				'form_action'    => Services::Request()->getUri(),
			],
			'imgs'             => [
				'svgs'           => [
					'exit'      => $con->svgs->iconClass( 'box-arrow-left' ),
					'help'      => $con->svgs->iconClass( 'question-circle' ),
					'ignore'    => $con->svgs->iconClass( 'eye-slash-fill' ),
					'megaphone' => $con->svgs->iconClass( 'megaphone' ),
					'video'     => $con->svgs->iconClass( 'youtube' ),
					'search'    => $con->svgs->iconClass( 'search' ),
					'settings'  => $con->svgs->iconClass( 'gear' ),
					'menu'      => $con->svgs->iconClass( 'card-list' ),
					'email'     => $con->svgs->iconClass( 'envelope-fill' ),
					'triangle'  => $con->svgs->iconClass( 'triangle-fill' ),
					'expand'    => $con->svgs->iconClass( 'chevron-bar-expand' ),
					'facebook'  => $con->svgs->iconClass( 'facebook' ),
					'twitter'   => $con->svgs->iconClass( 'twitter' ),
					'wordpress' => $con->svgs->iconClass( 'wordpress' ),
					'blog'      => $con->svgs->iconClass( 'file-text-fill' ),
					'upgrade'   => $con->svgs->iconClass( 'coin' ),
				],
				'favicon'        => $urlBuilder->forImage( 'pluginlogo_24x24.png' ),
			],
			'content'          => [
				'alt'          => '',
				'actions'      => '',
				'help'         => '',
			],
			'strings'          => $this->getDisplayStrings(),
		];
	}

	private function getDisplayStrings() :array {
		$con = self::con();
		$commonStrings = CommonDisplayStrings::all();

		return Services::DataManipulation()->mergeArraysRecursive(
			$commonStrings,
			[
				'on'                           => __( 'On', 'wp-simple-firewall' ),
				'off'                          => __( 'Off', 'wp-simple-firewall' ),
				'yes'                          => CommonDisplayStrings::get( 'yes_label' ),
				'no'                           => CommonDisplayStrings::get( 'no_label' ),
				'never'                        => CommonDisplayStrings::get( 'never_label' ),
				'opt_info_helpdesk'            => __( 'HelpDesk article', 'wp-simple-firewall' ),
				'opt_info_blog'                => __( 'Blog article', 'wp-simple-firewall' ),
				'opt_info_video'               => __( 'Watch the explainer video', 'wp-simple-firewall' ),
				'logged_in'                    => __( 'Logged-In', 'wp-simple-firewall' ),
				'blog'                         => __( 'Blog', 'wp-simple-firewall' ),
				'save_all_settings'            => __( 'Save Settings', 'wp-simple-firewall' ),
				'plugin_name'                  => $con->labels->Name,
				'admin_shell_title'            => sprintf( __( '%s admin page', 'wp-simple-firewall' ), $con->labels->Name ),
				'admin_shell_main_label'       => sprintf( __( '%s admin content', 'wp-simple-firewall' ), $con->labels->Name ),
				'admin_shell_sidebar_label'    => sprintf( __( '%s admin sidebar', 'wp-simple-firewall' ), $con->labels->Name ),
				'admin_shell_navigation_label' => sprintf( __( '%s admin navigation', 'wp-simple-firewall' ), $con->labels->Name ),
				'actions_title'                => __( 'Actions and Info', 'wp-simple-firewall' ),
				'installation_id'              => __( 'Installation ID', 'wp-simple-firewall' ),
				'select'                       => __( 'Select', 'wp-simple-firewall' ),
				'pro_only_option'              => __( 'Upgrade Required', 'wp-simple-firewall' ),
				'go_pro'                       => __( 'Go Pro!', 'wp-simple-firewall' ),

				'mode' => __( 'Mode', 'wp-simple-firewall' ),

				'dashboard' => __( 'Dashboard', 'wp-simple-firewall' ),

				'are_you_sure'                 => __( 'Are you sure?', 'wp-simple-firewall' ),
				'cancel'                       => __( 'Cancel', 'wp-simple-firewall' ),
				'close'                        => __( 'Close', 'wp-simple-firewall' ),
				'confirm'                      => __( 'Confirm', 'wp-simple-firewall' ),
				'dialog_alert_title'           => __( 'Notice', 'wp-simple-firewall' ),
				'dialog_confirm_title'         => __( 'Confirm Action', 'wp-simple-firewall' ),
				'dialog_prompt_title'          => __( 'Information Required', 'wp-simple-firewall' ),
				'description'                  => __( 'Description', 'wp-simple-firewall' ),
				'loading'                      => __( 'Loading', 'wp-simple-firewall' ),
				'request_failed'               => __( 'Request Failed', 'wp-simple-firewall' ),
				'scan_repair_limit_exceeded'   => __( "Sorry, this tool isn't designed for such large repairs. We recommend completely removing and reinstalling the item.", 'wp-simple-firewall' ),
				'aar_to_manage_must_enter_key' => __( 'PIN Required', 'wp-simple-firewall' ),
				'aar_enter_access_key'         => __( 'Security Admin PIN', 'wp-simple-firewall' ),
				'aar_submit_access_key'        => __( 'Submit PIN', 'wp-simple-firewall' ),
				'aar_forget_key'               => __( 'Forgotten PIN', 'wp-simple-firewall' ),

				'offense' => __( 'offense', 'wp-simple-firewall' ),
				'debug'   => CommonDisplayStrings::get( 'debug_label' ),

				'privacy_policy'         => __( 'Privacy Policy', 'wp-simple-firewall' ),

				'options'        => CommonDisplayStrings::get( 'options_label' ),
				'not_enabled'    => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),

				'wphashes_token' => sprintf( '%s API Token', self::con()->labels->Name ),

				'running_version_only' => $this->buildRunningVersionHtml(),

				'product_name'    => CommonDisplayStrings::get( 'name_label' ),
				'license_active'  => __( 'Active', 'wp-simple-firewall' ),
				'license_status'  => __( 'Status', 'wp-simple-firewall' ),
				'license_key'     => __( 'Key', 'wp-simple-firewall' ),
				'license_expires' => __( 'Expires', 'wp-simple-firewall' ),
				'license_email'   => __( 'Owner', 'wp-simple-firewall' ),
				'last_checked'    => __( 'Checked', 'wp-simple-firewall' ),

				'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $con->labels->Name ),
				'recommendation'      => \ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
				'suggestion'          => \ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),


				'search_shield'            => sprintf( __( 'Search %s', 'wp-simple-firewall' ), $con->labels->Name ),
				'search_shield_label'      => __( 'Click to launch plugin search modal', 'wp-simple-firewall' ),
				'search_modal_placeholder' => __( 'Search settings, tools, IP addresses and help', 'wp-simple-firewall' ),
				'search_modal_min_chars'   => __( 'Search using whole words of at least 3 characters.', 'wp-simple-firewall' ),
				'search_modal_you_could'   => __( 'You could search for', 'wp-simple-firewall' ),
				'search_suggestions'       => [
					sprintf( __( '%s options and modules', 'wp-simple-firewall' ), $con->labels->Name ),
					sprintf( __( '%s tools and features', 'wp-simple-firewall' ), $con->labels->Name ),
					__( 'IP addresses', 'wp-simple-firewall' ),
					__( 'Help docs & resources', 'wp-simple-firewall' ),
				],
				'translated_colon_space'   => __( ': ', 'wp-simple-firewall' ),
			] );
	}

	protected function getTwigEnvironmentVars() :array {
		return [
			'strict_variables' => false,
		];
	}

	protected function getTextInputFromRequestOrActionData( string $key, string $default = '' ) :string {
		$value = Services::Request()->query( $key, null );
		if ( $value === null && \array_key_exists( $key, $this->action_data ) ) {
			$value = $this->action_data[ $key ];
		}
		if ( $value === null ) {
			$value = $default;
		}
		return \trim( sanitize_text_field( \is_scalar( $value ) ? (string)$value : $default ) );
	}

	private function buildRunningVersionHtml() :string {
		$con = self::con();
		return Services::WpPlugins()->isUpdateAvailable( $con->base_file )
			? sprintf(
				'<a href="%s" target="_blank" class="text-danger shield-footer-version">%s</a>',
				Services::WpGeneral()->getAdminUrl_Updates(),
				$con->cfg->version()
			)
			: $con->cfg->version();
	}
}
