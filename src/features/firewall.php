<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_Firewall extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return array
	 */
	public function getDefaultWhitelist() {
		$aW = $this->getDef( 'default_whitelist' );
		return is_array( $aW ) ? $aW : [];
	}

	/**
	 * @param string $sParam
	 * @param string $sPage
	 * @return ICWP_WPSF_FeatureHandler_Firewall
	 */
	public function addParamToWhitelist( $sParam, $sPage = '*' ) {
		if ( empty( $sPage ) ) {
			$sPage = '*';
		}

		$aW = $this->getCustomWhitelist();
		$aParams = isset( $aW[ $sPage ] ) ? $aW[ $sPage ] : [];
		$aParams[] = $sParam;
		natsort( $aParams );
		$aW[ $sPage ] = array_unique( $aParams );

		return $this->setOpt( 'page_params_whitelist', $aW );
	}

	/**
	 * @return array
	 */
	public function getCustomWhitelist() {
		$aW = $this->getOpt( 'page_params_whitelist', [] );
		return is_array( $aW ) ? $aW : [];
	}

	/**
	 * @return string
	 */
	public function getBlockResponse() {
		$sBlockResponse = $this->getOpt( 'block_response', '' );
		return !empty( $sBlockResponse ) ? $sBlockResponse : 'redirect_die_message'; // TODO: use default
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
	 * @return bool
	 */
	public function isIgnoreAdmin() {
		return $this->isOpt( 'whitelist_admins', 'Y' );
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'Firewall', 'wp-simple-firewall' ),
				'sub'   => __( 'Block Malicious Requests', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aThis[ 'key_opts' ][ 'mod' ] = [
				'name'    => __( 'Firewall', 'wp-simple-firewall' ),
				'enabled' => $this->isModOptEnabled(),
				'summary' => $this->isModOptEnabled() ?
					__( 'Your site is protected against malicious requests', 'wp-simple-firewall' )
					: __( 'Your site is not protected against malicious requests', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( $this->getEnableModOptKey() ),
			];

			//ignoring admin isn't a good idea
			$bAdminIncluded = !$this->isIgnoreAdmin();
			$aThis[ 'key_opts' ][ 'admin' ] = [
				'name'    => __( 'Ignore Admins', 'wp-simple-firewall' ),
				'enabled' => $bAdminIncluded,
				'summary' => $bAdminIncluded ?
					__( "Firewall rules are also applied to admins", 'wp-simple-firewall' )
					: __( "Firewall rules aren't applied to admins", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'whitelist_admins' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @return Shield\Modules\Firewall\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Firewall\Options();
	}

	/**
	 * @return Shield\Modules\Firewall\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Firewall\Strings();
	}
}