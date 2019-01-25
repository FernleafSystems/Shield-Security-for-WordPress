<?php

class ICWP_WPSF_Processor_Plugin_BadgeWidget extends ICWP_WPSF_WpWidget {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

	/**
	 * ICWP_WPSF_Processor_Plugin_BadgeWidget constructor.
	 * @param ICWP_WPSF_FeatureHandler_Base $oMod
	 */
	public function __construct( $oMod ) {
		$this->setMod( $oMod );
		parent::__construct(
			$oMod->prefixOptionKey( 'plugin_badge' ),
			sprintf( _wpsf__( '%s Plugin Badge' ), $this->getCon()->getHumanName() ),
			array(
				'description' => sprintf( _wpsf__( 'You can now help spread the word about the %s plugin anywhere on your site' ), $this->getCon()
																																		->getHumanName() ),
			)
		);

		add_shortcode( 'SHIELD_BADGE', array( $this, 'renderBadge' ) );
	}

	/**
	 * @param array $aNewInstance
	 * @param array $aOldInstance
	 * @return array
	 */
	public function update( $aNewInstance, $aOldInstance ) {
		return parent::update( $aNewInstance, $aOldInstance );
//			$aInstance = array(
//				'title' => empty( $aNewInstance['title'] ) ? '' : strip_tags( $aNewInstance['title'] )
//			);
//			return $aInstance;
	}

	/**
	 * @param array $aWidgetArguments
	 * @param array $aWidgetInstance
	 * @throws \Exception
	 */
	public function widget( $aWidgetArguments, $aWidgetInstance ) {
		echo $this->standardRender( $aWidgetArguments, _wpsf__( 'Site Secured' ), $this->renderBadge() );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function renderBadge() {
		$oCon = $this->getCon();
		$aData = array(
			'strings' => array(
				'plugin_name' => $oCon->getHumanName(),
			),
			'hrefs'   => array(
				'img_src' => $oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' )
			)
		);

		return $this->getMod()
					->loadRenderer( $oCon->getPath_Templates().'php' )
					->setRenderVars( $aData )
					->setTemplate( 'snippets/plugin_badge_widget' )
					->setTemplateEnginePhp()
					->render();
	}
}