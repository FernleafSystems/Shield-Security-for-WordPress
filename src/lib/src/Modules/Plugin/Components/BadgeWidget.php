<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class BadgeWidget extends \WP_Widget {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

	/**
	 * BadgeWidget constructor.
	 * @param ModCon $mod
	 */
	public function __construct( $mod ) {
		if ( empty( $mod ) ) {
			return;
		}
		$this->setMod( $mod );
		$oCon = $this->getCon();

		$sName = $oCon->getHumanName();
		parent::__construct(
			$oCon->prefixOption( 'plugin_badge' ),
			sprintf( __( '%s Plugin Badge', 'wp-simple-firewall' ), $sName ),
			[
				'description' => sprintf( __( 'You can now help spread the word about the %s plugin anywhere on your site', 'wp-simple-firewall' ), $sName ),
			]
		);

		add_shortcode( 'SHIELD_BADGE', [ $this, 'renderBadge' ] );
	}

	/**
	 * @param array  $aWidgetArguments
	 * @param string $sTitle
	 * @param string $sContent
	 * @return string
	 */
	protected function standardRender( $aWidgetArguments, $sTitle = '', $sContent = '' ) {
		echo $aWidgetArguments[ 'before_widget' ];
		if ( !empty( $sTitle ) ) {
			echo $aWidgetArguments[ 'before_title' ].$sTitle.$aWidgetArguments[ 'after_title' ];
		}
		return $sContent.$aWidgetArguments[ 'after_widget' ];
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
		return ( new PluginBadge() )
			->setMod( $this->getMod() )
			->render();
	}
}