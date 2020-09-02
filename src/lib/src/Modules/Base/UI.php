<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UI {

	use ModConsumer;

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 */
	public function buildOptions() {

		$bPremiumEnabled = $this->getCon()->isPremiumExtensionsEnabled();

		$opts = $this->getOptions();
		$aOptions = $opts->getOptionsForPluginUse();

		foreach ( $aOptions as $nSectionKey => $aSection ) {

			if ( !empty( $aSection[ 'options' ] ) ) {

				foreach ( $aSection[ 'options' ] as $nKey => $aOption ) {
					$aOption[ 'is_value_default' ] = ( $aOption[ 'value' ] === $aOption[ 'default' ] );
					$bIsPrem = isset( $aOption[ 'premium' ] ) && $aOption[ 'premium' ];
					if ( !$bIsPrem || $bPremiumEnabled ) {
						$aSection[ 'options' ][ $nKey ] = $this->buildOptionForUi( $aOption );
					}
					else {
						unset( $aSection[ 'options' ][ $nKey ] );
					}
				}

				if ( empty( $aSection[ 'options' ] ) ) {
					unset( $aOptions[ $nSectionKey ] );
				}
				else {
					try {
						$aStrings = $this->getMod()
										 ->getStrings()
										 ->getSectionStrings( $aSection[ 'slug' ] );
						foreach ( $aStrings as $sKey => $sVal ) {
							unset( $aSection[ $sKey ] );
							$aSection[ $sKey ] = $sVal;
						}
					}
					catch ( \Exception $oE ) {
					}
					$aOptions[ $nSectionKey ] = $aSection;
				}

				$aWarnings = [];
				if ( !$opts->isSectionReqsMet( $aSection[ 'slug' ] ) ) {
					$aWarnings[] = __( 'Unfortunately your WordPress and/or PHP versions are too old to support this feature.', 'wp-simple-firewall' );
				}
				$aOptions[ $nSectionKey ][ 'warnings' ] = array_merge(
					$aWarnings,
					$this->getSectionWarnings( $aSection[ 'slug' ] )
				);
				$aOptions[ $nSectionKey ][ 'notices' ] = $this->getSectionNotices( $aSection[ 'slug' ] );
			}
		}

		return $aOptions;
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {

		$mCurrent = $aOptParams[ 'value' ];

		switch ( $aOptParams[ 'type' ] ) {

			case 'password':
				if ( !empty( $mCurrent ) ) {
					$mCurrent = '';
				}
				break;

			case 'array':

				if ( empty( $mCurrent ) || !is_array( $mCurrent ) ) {
					$mCurrent = [];
				}

				$aOptParams[ 'rows' ] = count( $mCurrent ) + 2;
				$mCurrent = stripslashes( implode( "\n", $mCurrent ) );

				break;

			case 'comma_separated_lists':

				$aNewValues = [];
				if ( !empty( $mCurrent ) && is_array( $mCurrent ) ) {

					foreach ( $mCurrent as $sPage => $aParams ) {
						$aNewValues[] = $sPage.', '.implode( ", ", $aParams );
					}
				}
				$aOptParams[ 'rows' ] = count( $aNewValues ) + 1;
				$mCurrent = implode( "\n", $aNewValues );

				break;

			case 'multiple_select':
				if ( !is_array( $mCurrent ) ) {
					$mCurrent = [];
				}
				break;

			case 'text':
				$mCurrent = stripslashes( $this->getMod()->getTextOpt( $aOptParams[ 'key' ] ) );
				break;
		}

		$aParams = [
			'value'    => is_scalar( $mCurrent ) ? esc_attr( $mCurrent ) : $mCurrent,
			'disabled' => !$this->getCon()
								->isPremiumActive() && ( isset( $aOptParams[ 'premium' ] ) && $aOptParams[ 'premium' ] ),
		];
		$aParams[ 'enabled' ] = !$aParams[ 'disabled' ];
		$aOptParams = array_merge( [ 'rows' => 2 ], $aOptParams, $aParams );

		// add strings
		try {
			$aOptStrings = $this->getMod()->getStrings()->getOptionStrings( $aOptParams[ 'key' ] );
			if ( is_array( $aOptStrings[ 'description' ] ) ) {
				$aOptStrings[ 'description' ] = implode( "<br/>", $aOptStrings[ 'description' ] );
			}
			$aOptParams = Services::DataManipulation()->mergeArraysRecursive( $aOptParams, $aOptStrings );
		}
		catch ( \Exception $oE ) {
		}
		return $aOptParams;
	}

	/**
	 * @return array
	 */
	public function getBaseDisplayData() {
		$mod = $this->getMod();
		$con = $this->getCon();

		/** @var Shield\Modules\Plugin\Options $oPluginOptions */
		$oPluginOptions = $con->getModule_Plugin()->getOptions();

		return [
			'sPluginName'   => $con->getHumanName(),
			'sTagline'      => $this->getOptions()->getFeatureTagline(),
			'nonce_field'   => wp_nonce_field( $con->getPluginPrefix(), '_wpnonce', true, false ), //don't echo!
			'form_action'   => 'admin.php?page='.$mod->getModSlug(),
			'aPluginLabels' => $con->getLabels(),
			'help_video'    => [
				'auto_show'   => $this->getIfAutoShowHelpVideo(),
				'display_id'  => 'ShieldHelpVideo'.$mod->getSlug(),
				'options'     => $this->getHelpVideoOptions(),
				'displayable' => $this->isHelpVideoDisplayable(),
				'show'        => $this->isHelpVideoDisplayable() && !$this->getHelpVideoHasBeenClosed(),
				'width'       => 772,
				'height'      => 454,
			],

			'aSummaryData'  => array_filter(
				$mod->getModulesSummaryData(),
				function ( $summary ) {
					return $summary[ 'show_mod_opts' ];
				}
			),

			'sPageTitle' => $mod->getMainFeatureName(),
			'data'       => [
				'mod_slug'       => $mod->getModSlug( true ),
				'mod_slug_short' => $mod->getModSlug( false ),
				'all_options'    => $this->buildOptions(),
				'xferable_opts'  => ( new Shield\Modules\Plugin\Lib\ImportExport\Options\BuildTransferableOptions() )
					->setMod( $mod )
					->build(),
				'hidden_options' => $this->getOptions()->getHiddenOptions()
			],
			'ajax'       => [
				'mod_options' => $mod->getAjaxActionData( 'mod_options' ),
			],
			'vendors'    => [
				'widget_freshdesk' => '3000000081' /* TODO: plugin spec config */
			],
			'strings'    => $mod->getStrings()->getDisplayStrings(),
			'flags'      => [
				'access_restricted'     => !$mod->canDisplayOptionsForm(),
				'show_ads'              => $mod->getIsShowMarketing(),
				'wrap_page_content'     => true,
				'show_standard_options' => true,
				'show_content_help'     => true,
				'show_alt_content'      => false,
				'has_wizard'            => $mod->hasWizard(),
				'is_premium'            => $con->isPremiumActive(),
				'show_transfer_switch'  => $con->isPremiumActive(),
				'is_wpcli'              => $oPluginOptions->isEnabledWpcli()
			],
			'hrefs'      => [
				'go_pro'         => 'https://shsec.io/shieldgoprofeature',
				'goprofooter'    => 'https://shsec.io/goprofooter',
				'wizard_link'    => $mod->getUrl_WizardLanding(),
				'wizard_landing' => $mod->getUrl_WizardLanding(),

				'form_action'      => Services::Request()->getUri(),
				'css_bootstrap'    => $con->getPluginUrl_Css( 'bootstrap4.min' ),
				'css_pages'        => $con->getPluginUrl_Css( 'pages' ),
				'css_steps'        => $con->getPluginUrl_Css( 'jquery.steps' ),
				'css_fancybox'     => $con->getPluginUrl_Css( 'jquery.fancybox.min' ),
				'css_globalplugin' => $con->getPluginUrl_Css( 'global-plugin' ),
				'css_wizard'       => $con->getPluginUrl_Css( 'wizard' ),
				'js_jquery'        => Services::Includes()->getUrl_Jquery(),
				'js_bootstrap'     => $con->getPluginUrl_Js( 'bootstrap4.bundle.min' ),
				'js_fancybox'      => $con->getPluginUrl_Js( 'jquery.fancybox.min' ),
				'js_globalplugin'  => $con->getPluginUrl_Js( 'global-plugin' ),
				'js_steps'         => $con->getPluginUrl_Js( 'jquery.steps.min' ),
				'js_wizard'        => $con->getPluginUrl_Js( 'wizard' ),
			],
			'imgs'       => [
				'favicon'       => $con->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
				'plugin_banner' => $con->getPluginUrl_Image( 'banner-1500x500-transparent.png' ),
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

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() {
		return [];
	}

	/**
	 * @return array
	 */
	protected function getModDisabledInsight() {
		$mod = $this->getMod();
		return [
			'name'    => __( 'Module Disabled', 'wp-simple-firewall' ),
			'enabled' => false,
			'summary' => __( 'All features of this module are completely disabled', 'wp-simple-firewall' ),
			'weight'  => 2,
			'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
		];
	}

	protected function getHelpVideoOptions() {
		$aOptions = $this->getOptions()->getOpt( 'help_video_options', [] );
		if ( is_null( $aOptions ) || !is_array( $aOptions ) ) {
			$aOptions = [
				'closed'    => false,
				'displayed' => false,
				'played'    => false,
			];
			$this->getOptions()->setOpt( 'help_video_options', $aOptions );
		}
		return $aOptions;
	}

	/**
	 * @param string $sId
	 * @return string
	 */
	protected function getHelpVideoUrl( $sId ) {
		return sprintf( 'https://player.vimeo.com/video/%s', $sId );
	}

	/**
	 * @return bool
	 */
	protected function getIfAutoShowHelpVideo() {
		return !$this->getHelpVideoHasBeenClosed();
	}

	/**
	 * @return bool
	 */
	protected function getHelpVideoHasBeenDisplayed() {
		return (bool)$this->getHelpVideoOption( 'displayed' );
	}

	/**
	 * @return bool
	 */
	protected function getVideoHasBeenPlayed() {
		return (bool)$this->getHelpVideoOption( 'played' );
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getHelpVideoOption( $sKey ) {
		$aOpts = $this->getHelpVideoOptions();
		return isset( $aOpts[ $sKey ] ) ? $aOpts[ $sKey ] : null;
	}

	/**
	 * @return bool
	 */
	protected function getHelpVideoHasBeenClosed() {
		return (bool)$this->getHelpVideoOption( 'closed' );
	}

	/**
	 * @return bool
	 */
	protected function isHelpVideoDisplayable() {
		return false;
	}

	/**
	 * @return string
	 */
	protected function getHelpVideoId() {
		return $this->getOptions()->getDef( 'help_video_id' );
	}

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionNotices( $section ) {
		return [];
	}

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionWarnings( $section ) {
		return [];
	}

	/**
	 * @return bool
	 */
	public function isEnabledForUiSummary() {
		return $this->getMod()->isModuleEnabled();
	}
}