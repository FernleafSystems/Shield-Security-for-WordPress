<?php
if ( !class_exists( 'ICWP_WPSF_WpWidget', false ) ):
	/**
	 * Class ICWP_WPSF_WpWidget_V1
	 */
	class ICWP_WPSF_WpWidget extends WP_Widget {

		/**
		 * @param array $aWidgetArguments
		 * @param string $sTitle
		 * @param string $sContent
		 */
		protected function standardRender( $aWidgetArguments, $sTitle = '', $sContent = '' ) {
			echo $aWidgetArguments[ 'before_widget' ];
			if ( !empty( $sTitle ) ) {
				echo $aWidgetArguments[ 'before_title' ] . $sTitle . $aWidgetArguments[ 'after_title' ];
			}
			echo $sContent.$aWidgetArguments[ 'after_widget' ];
		}
	}
endif;