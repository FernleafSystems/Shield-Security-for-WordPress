<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\RequestVO {

	protected function getDefaultFilterFields() :array {
		return [
			'license',
			'item_name',
			'url',
			'customer_email',
			'expires',
			'expires_at',
			'is_trial',
			'install_id',
			'last_verified_at',
		];
	}
}