<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $Name
 * @property string $Description
 * @property string $Title
 * @property string $Author
 * @property string $AuthorName
 * @property string $MenuTitle
 * @property string $PluginURI
 * @property string $AuthorURI
 * @property string $url_secadmin_forgotten_key
 * @property string $url_helpdesk
 * @property string $url_img_pagebanner
 * @property string $url_img_logo_small
 * @property string $icon_url_16x16
 * @property string $icon_url_16x16_grey
 * @property string $icon_url_32x32
 * @property string $icon_url_128x128
 * @property bool   $is_whitelabelled
 */
class Labels extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'Name':
				$value = (string)$value;
				break;
			default:
				break;
		}

		return $value;
	}
}