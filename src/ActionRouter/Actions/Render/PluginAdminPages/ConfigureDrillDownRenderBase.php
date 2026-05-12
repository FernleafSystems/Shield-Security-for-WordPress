<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class ConfigureDrillDownRenderBase extends DrillDownAjaxRenderBase {

	use BuildsConfigureLandingData;

	/**
	 * @throws ActionException
	 */
	protected function getSelectedConfigureZoneKey() :string {
		$zoneKey = sanitize_key( (string)( $this->action_data[ 'zone' ] ?? '' ) );
		if ( $zoneKey === '' || !isset( $this->getConfigureLandingTileLookup()[ $zoneKey ] ) ) {
			throw new ActionException( 'Invalid Configure zone key.' );
		}
		return $zoneKey;
	}
}
