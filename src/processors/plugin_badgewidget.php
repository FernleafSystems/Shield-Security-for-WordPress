<?php

/**
 * Class ICWP_WPSF_Processor_Plugin_BadgeWidget
 * @deprecated 8.0.1
 */
class ICWP_WPSF_Processor_Plugin_BadgeWidget extends ICWP_WPSF_WpWidget {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

	/**
	 * ICWP_WPSF_Processor_Plugin_BadgeWidget constructor.
	 * @param ICWP_WPSF_FeatureHandler_Base $oMod
	 */
	public function __construct( $oMod ) {
		return;
	}

	/**
	 * @param array $aWidgetArguments
	 * @param array $aWidgetInstance
	 * @throws \Exception
	 */
	public function widget( $aWidgetArguments, $aWidgetInstance ) {
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function renderBadge() {
		return '';
	}
}