<?php

class ICWP_WPSF_Processor_Plugin_BadgeWidget extends ICWP_WPSF_WpWidget {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

	/**
	 * ICWP_WPSF_Processor_Plugin_BadgeWidget constructor.
	 * @param ICWP_WPSF_FeatureHandler_Base $oMod
	 */
	public function __construct( $oMod ) {
		if ( empty( $oMod ) ) {
			try {
				$oMod = ICWP_WPSF_Shield_Security::GetInstance()
												 ->getController()
												 ->getModule_Plugin();
			}
			catch ( \Exception $oE ) {
				return;
			}
		}
		$this->setMod( $oMod );
		$oCon = $this->getCon();
		parent::__construct(
			$oCon->prefixOption( 'plugin_badge' ),
			sprintf( __( '%s Plugin Badge', 'wp-simple-firewall' ), $oCon->getHumanName() ),
			[
				'description' => sprintf( __( 'You can now help spread the word about the %s plugin anywhere on your site', 'wp-simple-firewall' ), $this->getCon()
																																						 ->getHumanName() ),
			]
		);

		add_shortcode( 'SHIELD_BADGE', [ $this, 'renderBadge' ] );
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
		echo $this->standardRender( $aWidgetArguments, __( 'Site Secured', 'wp-simple-firewall' ), $this->renderBadge() );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function renderBadge() {
		$oCon = $this->getCon();
		$aData = [
			'strings' => [
				'plugin_name' => $oCon->getHumanName(),
			],
			'hrefs'   => [
				'img_src' => $oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' )
			]
		];

		return $this->getMod()
					->renderTemplate( 'snippets/plugin_badge_widget', $aData );
	}
}