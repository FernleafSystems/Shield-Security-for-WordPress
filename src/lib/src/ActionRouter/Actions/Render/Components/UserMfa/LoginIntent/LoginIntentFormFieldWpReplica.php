<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa\LoginIntent;

class LoginIntentFormFieldWpReplica extends LoginIntentFormFieldBase {

	public const SLUG = 'user_mfa_login_intent_form_field_wp_replica';
	public const TEMPLATE = '/components/wplogin_replica/login_field_%s.twig';
}