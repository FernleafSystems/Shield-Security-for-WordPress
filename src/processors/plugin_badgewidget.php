<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin_BadgeWidget', false ) ):

	class ICWP_WPSF_Processor_Plugin_BadgeWidget extends ICWP_WPSF_WpWidget {
		/**
		 * @var ICWP_WPSF_FeatureHandler_Base
		 */
		protected static $oFeatureOptions;

		/**
		 * ICWP_WPSF_Processor_Plugin_BadgeWidget constructor.
		 */
		public function __construct() {
			parent::__construct(
				self::$oFeatureOptions->prefixOptionKey( 'plugin_badge' ),
				sprintf( _wpsf__( '%s Plugin Badge' ), self::$oFeatureOptions->getController()->getHumanName() ),
				array(
					'description' => sprintf( _wpsf__( 'You can now help spread the word about the %s plugin anywhere on your site' ), self::$oFeatureOptions->getController()->getHumanName() ),
				)
			);

			add_shortcode( 'SHIELD_BADGE', array( $this, 'renderBadge' ) );
		}

		/**
		 * @param $oFeatureOptions
		 */
		public static function SetFeatureOptions( $oFeatureOptions ) {
			self::$oFeatureOptions = $oFeatureOptions;
		}

		/**
		 * @param array $aNewInstance
		 * @param array $aOldInstance
		 * @return array
		 */
		public function update( $aNewInstance, $aOldInstance ) {
			parent::update( $aNewInstance, $aOldInstance );
//			$aInstance = array(
//				'title' => empty( $aNewInstance['title'] ) ? '' : strip_tags( $aNewInstance['title'] )
//			);
//			return $aInstance;
		}

		/**
		 * @param array $aWidgetArguments
		 * @param array $aWidgetInstance
		 */
		public function widget( $aWidgetArguments, $aWidgetInstance ) {
			echo $this->standardRender( $aWidgetArguments, _wpsf__( 'Site Secured' ), $this->renderBadge() );
		}

		/**
		 * @return string
		 */
		public function renderBadge() {
			$oCon = self::$oFeatureOptions->getController();
			$aData = array(
				'strings' => array(
					'plugin_name' => $oCon->getHumanName(),
				),
				'hrefs'		=> array(
					'img_src' => $oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' )
				)
			);

			return self::$oFeatureOptions
				->loadRenderer( $oCon->getPath_Templates().'php' )
				->setRenderVars( $aData )
				->setTemplate( 'snippets'.DIRECTORY_SEPARATOR.'plugin_badge_widget' )
				->setTemplateEnginePhp()
				->render();
		}
	}

endif;