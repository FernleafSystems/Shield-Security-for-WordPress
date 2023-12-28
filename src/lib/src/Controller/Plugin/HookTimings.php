<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

class HookTimings {

	public const INIT_DEFAULT_RULES_HOOK = -2000;
	public const INIT_MAIN_CONTROLLER = -1000;
	public const INIT_ANTIBOT_SETUP = -100;
	public const INIT_USER_FORMS_SETUP = -100;
	public const INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_SHIELD = 3;
	public const INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_CROWDSEC = 4;
	public const INIT_MOD_CON_DEFAULT = 5;
	public const INIT_PROCESSOR_DEFAULT = 9;
	public const INIT_LOGIN_INTENT_REQUEST_CAPTURE = 9;
	public const INIT_LOGIN_RENAME = 9;
	public const WPLOADED_ADMIN_PAGE_HANDLER = 5;
}