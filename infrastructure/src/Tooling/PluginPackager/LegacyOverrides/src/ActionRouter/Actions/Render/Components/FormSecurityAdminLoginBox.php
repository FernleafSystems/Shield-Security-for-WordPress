<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;

class FormSecurityAdminLoginBox extends BaseAction {

	public const SLUG = 'render_form_security_admin_loginbox';

	protected function checkAccess() {
	}

	protected function exec() {
		$this->response()->mergePayload( [
			'render_output' => '',
			'html'          => '',
		] );
	}
}
