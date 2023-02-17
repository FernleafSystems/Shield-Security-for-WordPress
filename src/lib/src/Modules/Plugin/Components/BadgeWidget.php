<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class BadgeWidget extends \WP_Widget {

	use ModConsumer;

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
	 * @param array  $widgetArgs
	 * @param string $title
	 * @param string $content
	 * @return string
	 */
	protected function standardRender( $widgetArgs, $title = '', $content = '' ) {
		echo $widgetArgs[ 'before_widget' ];
		if ( !empty( $title ) ) {
			echo $widgetArgs[ 'before_title' ].$title.$widgetArgs[ 'after_title' ];
		}
		return $content.$widgetArgs[ 'after_widget' ];
	}

	/**
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return parent::update( $new_instance, $old_instance );
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