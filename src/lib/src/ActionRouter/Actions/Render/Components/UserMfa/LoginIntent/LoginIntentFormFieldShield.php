<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa\LoginIntent;

class LoginIntentFormFieldShield extends LoginIntentFormFieldBase {

	public const SLUG = 'user_mfa_login_intent_form_field_shield';
	public const TEMPLATE = '/components/login_intent/login_field_%s.twig';
}