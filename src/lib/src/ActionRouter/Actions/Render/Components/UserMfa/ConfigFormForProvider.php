<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;

class ConfigFormForProvider extends UserMfaBase {

	use AnyUserAuthRequired;

	public const SLUG = 'user_mfa_config_form_provider';
	public const TEMPLATE = '/user/profile/mfa/provider_%s.twig';

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'provider_slug' ] );
	}
}