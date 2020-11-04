<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_FeatureHandler_Firewall extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param string $sParam
	 * @param string $sPage
	 */
	public function addParamToWhitelist( $sParam, $sPage = '*' ) {
		/** @var Firewall\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( empty( $sPage ) ) {
			$sPage = '*';
		}

		$aW = $oOpts->getCustomWhitelist();
		$aParams = isset( $aW[ $sPage ] ) ? $aW[ $sPage ] : [];
		$aParams[] = $sParam;
		natsort( $aParams );
		$aW[ $sPage ] = array_unique( $aParams );

		$oOpts->setOpt( 'page_params_whitelist', $aW );
	}

	/**
	 * @return string
	 */
	public function getBlockResponse() {
		$response = $this->getOptions()->getOpt( 'block_response', '' );
		return !empty( $response ) ? $response : 'redirect_die_message'; // TODO: use default
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {
			case 'text_firewalldie':
				$sText = sprintf(
					__( "You were blocked by the %s.", 'wp-simple-firewall' ),
					'<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getCon()
																										->getHumanName().'</a>'
				);
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Firewall';
	}
}