<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $provider
 * @property string $key
 * @property string $secret
 * @property string $theme
 * @property bool   $invisible
 * @property bool   $ready
 * @property string $url_api
 * @property string $js_handle
 */
class CaptchaConfigVO extends DynPropertiesClass {

	const PROV_GOOGLE_RECAP2 = 'grecaptcha';
	const PROV_HCAPTCHA = 'hcaptcha';

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'ready':
				$value = !empty( $this->key ) && !empty( $this->secret );
				break;

			default:
				break;
		}

		return $value;
	}
}
