<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	/**
	 * @param string $sParam
	 * @param string $sPage
	 */
	public function addParamToWhitelist( $sParam, $sPage = '*' ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( empty( $sPage ) ) {
			$sPage = '*';
		}

		$aW = $opts->getCustomWhitelist();
		$aParams = isset( $aW[ $sPage ] ) ? $aW[ $sPage ] : [];
		$aParams[] = $sParam;
		natsort( $aParams );
		$aW[ $sPage ] = array_unique( $aParams );

		$opts->setOpt( 'page_params_whitelist', $aW );
	}

	public function getBlockResponse() :string {
		$response = $this->getOptions()->getOpt( 'block_response', '' );
		return !empty( $response ) ? $response : 'redirect_die_message'; // TODO: use default
	}

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {
			case 'text_firewalldie':
				$text = sprintf(
					__( "You were blocked by the %s Firewall.", 'wp-simple-firewall' ),
					'<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getCon()
																										->getHumanName().'</a>'
				);
				break;

			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}
}