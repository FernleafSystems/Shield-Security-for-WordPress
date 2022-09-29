<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\UserMfa;

class ConfigFormForProvider extends UserMfaBase {

	const SLUG = 'user_mfa_config_form_provider';
	const TEMPLATE = '/user/profile/mfa/provider_%s.twig';

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'provider_slug' ] );
	}
}