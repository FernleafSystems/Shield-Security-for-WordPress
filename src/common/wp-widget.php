<?php

/**
 * Class ICWP_WPSF_WpWidget
 */
abstract class ICWP_WPSF_WpWidget extends WP_Widget {

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
}