<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

class SecurityAdminRemove extends SecurityAdminBase {

	use NonceVerifyRequired;

	public const SLUG = 'secadmin_remove_confirm';

	protected function exec() {
		( new RemoveSecAdmin() )->remove( (bool)( $this->action_data[ 'quietly' ] ?? false ) );
		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => self::con()->plugin_urls->zone( Secadmin::Slug() ),
		];
	}
}