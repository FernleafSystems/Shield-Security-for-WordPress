<?php

if ( class_exists( 'ICWP_WPSF_Processor_AdminAccess_Whitelabel', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_AdminAccess_Whitelabel extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		add_filter( $this->getController()->doPluginPrefix( 'plugin_labels' ), array( $this, 'doRelabelPlugin' ) );
		add_filter( 'plugin_row_meta', array( $this, 'fRemoveDetailsMetaLink' ), 200, 2 );
	}

	/**
	 * @param array $aPluginLabels
	 * @return array
	 */
	public function doRelabelPlugin( $aPluginLabels ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();

		$aWhiteLabels = $oFO->getWhitelabelOptions();

		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$sServiceName = $aWhiteLabels[ 'name' ];
		if ( !empty( $sServiceName ) ) {
			$aPluginLabels[ 'Name' ] = $sServiceName;
			$aPluginLabels[ 'Title' ] = $sServiceName;
			$aPluginLabels[ 'Author' ] = $sServiceName;
			$aPluginLabels[ 'AuthorName' ] = $sServiceName;
		}
		$sTagLine = $aWhiteLabels[ 'description' ];
		if ( !empty( $sTagLine ) ) {
			$aPluginLabels[ 'Description' ] = $sTagLine;
		}

		$sUrl = $aWhiteLabels[ 'url_home' ];
		if ( !empty( $sUrl ) ) {
			$aPluginLabels[ 'PluginURI' ] = $sUrl;
			$aPluginLabels[ 'AuthorURI' ] = $sUrl;
		}

		$sIconUrl = $aWhiteLabels[ 'url_icon' ];
		if ( !empty( $sIcon16 ) ) {
			$aPluginLabels[ 'icon_url_16x16' ] = $sIconUrl;
		}

		$sIcon32 = $this->getOption( 'icon_url_32x32' );
		if ( !empty( $sIcon32 ) ) {
			$aPluginLabels[ 'icon_url_32x32' ] = $sIconUrl;
		}

		return $aPluginLabels;
	}

	/**
	 * @filter
	 * @param array  $aPluginMeta
	 * @param string $sPluginBaseFileName
	 * @return array
	 */
	public function fRemoveDetailsMetaLink( $aPluginMeta, $sPluginBaseFileName ) {
		if ( $sPluginBaseFileName == $this->getController()->getPluginBaseFile() ) {
			unset( $aPluginMeta[ 2 ] ); // View details
			unset( $aPluginMeta[ 3 ] ); // Rate 5*
		}
		return $aPluginMeta;
	}
}