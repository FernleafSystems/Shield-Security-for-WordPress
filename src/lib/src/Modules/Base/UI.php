<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UI {

	use ModConsumer;

	public function buildOptionsForStandardUI() :array {
		return ( new Options\BuildForDisplay() )
			->setMod( $this->getMod() )
			->setIsWhitelabelled( $this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() )
			->standard();
	}

	public function buildSelectData_OptionsSearch() :array {
		$searchSelect = [];
		foreach ( $this->getCon()->modules as $module ) {
			$cfg = $module->cfg;
			if ( $cfg->properties[ 'show_module_options' ] ) {
				$options = [];
				foreach ( $module->getOptions()->getVisibleOptionsKeys() as $optKey ) {
					try {
						$options[ $optKey ] = array_merge(
							$module->getStrings()->getOptionStrings( $optKey ),
							[
								'href' => $module->getUrl_DirectLinkToOption( $optKey )
							]
						);
					}
					catch ( \Exception $e ) {
					}
				}
				$searchSelect[ $module->getMainFeatureName() ] = $options;
			}
		}
		return $searchSelect;
	}

	public function getBaseDisplayData() :array {
		$mod = $this->getMod();
		$con = $this->getCon();
		$urlBuilder = $con->urls;

		/** @var Shield\Modules\Plugin\Options $pluginOptions */
		$pluginOptions = $con->getModule_Plugin()->getOptions();

		return [
			'sTagline'    => $mod->cfg->properties[ 'tagline' ],
			'nonce_field' => wp_nonce_field( $con->getPluginPrefix(), '_wpnonce', true, false ),

			'sPageTitle' => $mod->getMainFeatureName(),
			'ajax'       => [
			],
			'vars'       => [
				'mod_slug'         => $mod->getModSlug(),
				'unique_render_id' => uniqid(),
			],
			'strings'    => $mod->getStrings()->getDisplayStrings(),
			'flags'      => [
				'access_restricted'     => $mod->isAccessRestricted(),
				'show_ads'              => $mod->getIsShowMarketing(),
				'wrap_page_content'     => true,
				'show_standard_options' => true,
				'show_content_help'     => true,
				'show_alt_content'      => false,
				'has_wizard'            => $mod->hasWizard(),
				'is_premium'            => $con->isPremiumActive(),
				'show_transfer_switch'  => $con->isPremiumActive(),
				'is_wpcli'              => $pluginOptions->isEnabledWpcli(),
			],
			'hrefs'      => [
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
					'ignore'   => $con->svgs->raw( 'bootstrap/eye-slash-fill.svg' ),
					'triangle' => $con->svgs->raw( 'bootstrap/triangle-fill.svg' ),
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
			]
		];
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