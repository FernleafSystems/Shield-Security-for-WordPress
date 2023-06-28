<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

class Constants {

	/**
	 * @var Auditors\Base[]|string[]
	 */
	public const AUDITORS = [
		Auditors\Wordpress::class,
		Auditors\Plugins::class,
		Auditors\Themes::class,
		Auditors\Users::class,
		Auditors\Database::class,
		Auditors\Posts::class,
		Auditors\Pages::class,
		Auditors\Comments::class,
		Auditors\Emails::class,
	];
	public const THIRDPARTY_AUDITORS = [
	];
}