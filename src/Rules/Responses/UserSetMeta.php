<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class UserSetMeta extends Base {

	public function execResponse() :void {
		$id = Services::WpUsers()->getCurrentWpUserId();
		if ( $id > 0 ) {
			update_user_meta( $id, $this->p->key, $this->p->value );
		}
	}

	public function getParamsDef() :array {
		return [
			'key'   => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => __( 'Meta Key', 'wp-simple-firewall' ),
				'verify_regex' => '/^[A-Za-z0-9_]+$/'
			],
			'value' => [
				'type'  => EnumParameters::TYPE_SCALAR,
				'label' => __( 'Meta Value', 'wp-simple-firewall' ),
			],
		];
	}
}