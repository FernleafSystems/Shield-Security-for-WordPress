<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class CaptchaConfigVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha
 * @property string $provider
 * @property string $key
 * @property string $secret
 * @property string $theme
 * @property bool   $invisible
 * @property bool   $ready
 * @property string $url_api
 * @property string $js_handle
 */
class CaptchaConfigVO {

	const PROV_GOOGLE_RECAP2 = 'grecaptcha';
	const PROV_HCAPTCHA = 'hcaptcha';
	use StdClassAdapter {
		__get as __adapterGet;
	}

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mValue = $this->__adapterGet( $sProperty );

		switch ( $sProperty ) {

			case 'ready':
				$mValue = !empty( $this->key ) && !empty( $this->secret );
				break;

			default:
				break;
		}

		return $mValue;
	}
}
