<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UI {

	use ModConsumer;

	public function getBaseDisplayData() :array {
		$mod = $this->getMod();
		$con = $this->getCon();
		$urlBuilder = $con->urls;

		/** @var Shield\Modules\Plugin\Options $pluginOptions */
		$pluginOptions = $con->getModule_Plugin()->getOptions();

		$isWhitelabelled = $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled();
		return [
			'sTagline'    => $mod->cfg->properties[ 'tagline' ],
			'nonce_field' => wp_nonce_field( $con->getPluginPrefix(), '_wpnonce', true, false ),

			'sPageTitle' => $mod->getMainFeatureName(),
			'ajax'       => [
			],
			'strings'    => $mod->getStrings()->getDisplayStrings(),
			'flags'      => [
				'is_mode_live'          => $con->is_mode_live,
				'access_restricted'     => method_exists( $mod, 'isAccessRestricted' ) && $mod->isAccessRestricted(),
				'show_ads'              => $mod->getIsShowMarketing(),
				'wrap_page_content'     => true,
				'show_standard_options' => true,
				'show_content_help'     => true,
				'show_alt_content'      => false,
				'has_wizard'            => $mod->hasWizard(),
				'is_premium'            => $con->isPremiumActive(),
				'is_whitelablled'       => $isWhitelabelled,
				'show_transfer_switch'  => $con->isPremiumActive(),
				'is_wpcli'              => $pluginOptions->isEnabledWpcli(),
			],
			'hrefs'      => [
				'helpdesk'       => $con->labels->url_helpdesk,
				'plugin_home'    => $con->labels->PluginURI,
				'go_pro'         => 'https://shsec.io/shieldgoprofeature',
				'goprofooter'    => 'https://shsec.io/goprofooter',
				'wizard_link'    => $mod->getUrl_WizardLanding(),
				'wizard_landing' => $mod->getUrl_WizardLanding(),

				'form_action'      => Services::Request()->getUri(),
				'css_bootstrap'    => $urlBuilder->forCss( 'bootstrap' ),
				'css_pages'        => $urlBuilder->forCss( 'shield/pages' ),
				'css_steps'        => $urlBuilder->forCss( 'jquery.steps' ),
				'css_fancybox'     => $urlBuilder->forCss( 'jquery.fancybox.min' ),
				'css_globalplugin' => $urlBuilder->forCss( 'global-plugin' ),
				'css_wizard'       => $urlBuilder->forCss( 'wizard' ),
				'js_jquery'        => Services::Includes()->getUrl_Jquery(),
				'js_bootstrap'     => $urlBuilder->forJs( 'bootstrap' ),
				'js_fancybox'      => $urlBuilder->forJs( 'jquery.fancybox.min' ),
				'js_globalplugin'  => $urlBuilder->forJs( 'global-plugin' ),
				'js_steps'         => 'https://cdnjs.cloudflare.com/ajax/libs/jquery-steps/1.1.0/jquery.steps.min.js',
			],
			'imgs'       => [
				'svgs'           => [
					'search'    => $con->svgs->raw( 'bootstrap/search.svg' ),
					'help'      => $con->svgs->raw( 'bootstrap/question-circle.svg' ),
					'helpdesk'  => $con->svgs->raw( 'bootstrap/life-preserver.svg' ),
					'ignore'    => $con->svgs->raw( 'bootstrap/eye-slash-fill.svg' ),
					'triangle'  => $con->svgs->raw( 'bootstrap/triangle-fill.svg' ),
					'megaphone' => $con->svgs->raw( 'bootstrap/megaphone.svg' ),
				],
				'favicon'        => $urlBuilder->forImage( 'pluginlogo_24x24.png' ),
				'plugin_banner'  => $urlBuilder->forImage( 'banner-1500x500-transparent.png' ),
				'background_svg' => $urlBuilder->forImage( 'shield/background-blob.svg' )
			],
			'content'    => [
				'options_form'   => '',
				'alt'            => '',
				'actions'        => '',
				'help'           => '',
				'wizard_landing' => ''
			],
			'vars'       => [
				'mod_slug'         => $mod->getModSlug(),
				'unique_render_id' => uniqid(),
			],
		];
	}

	/**
	 * @param string $for - option, section, module
	 */
	public function getOffCanvasJavascriptLinkFor( string $for ) :string {
		return sprintf( "javascript:{iCWP_WPSF_ConfigCanvas.renderConfig('%s')}", $for );
	}

	protected function getHelpVideoUrl( string $id ) :string {
		return sprintf( 'https://player.vimeo.com/video/%s', $id );
	}

	public function getSectionNotices( string $section ) :array {
		return [];
	}

	public function getSectionWarnings( string $section ) :array {
		return [];
	}
}