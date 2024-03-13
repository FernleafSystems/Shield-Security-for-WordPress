<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsSettingsLookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan\CommentSpamCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\FileLockerController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\SpamController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\UserFormsController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller as MainwpCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\NotBotHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\LicenseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\WpHashes\ApiTokenManager;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components\PluginBadge;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\MerlinController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportingController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions\SessionController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\SecurityAdminController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel\WhitelabelController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogger;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend\UserSuspendController;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\ShieldNetApiController;

/**
 * @property AuditCon                  $activity_log
 * @property ApiTokenManager           $api_token
 * @property CompCons\AutoUpdatesCon   $autoupdates
 * @property PluginBadge               $badge
 * @property BotSignalsController      $bot_signals
 * @property CommentSpamCon            $comment_spam
 * @property CrowdSecController        $crowdsec
 * @property FileLockerController      $file_locker
 * @property SpamController            $forms_spam
 * @property UserFormsController       $forms_users
 * @property CompCons\HttpHeadersCon   $http_headers
 * @property ImportExportController    $import_export
 * @property CompCons\InstantAlertsCon $instant_alerts
 * @property CompCons\IPsCon           $ips_con
 * @property LicenseHandler            $license
 * @property MainwpCon                 $mainwp
 * @property MfaController             $mfa
 * @property NotBotHandler             $not_bot
 * @property OffenseTracker            $offense_tracker
 * @property OptsSettingsLookup        $opts_lookup
 * @property ReportingController       $reports
 * @property RequestLogger             $requests_log
 * @property SecurityAdminController   $sec_admin
 * @property SessionController         $session
 * @property Scan\ScansController      $scans
 * @property Scan\Queue\Controller     $scans_queue
 * @property ShieldNetApiController    $shieldnet
 * @property UserSuspendController     $user_suspend
 * @property WhitelabelController      $whitelabel
 * @property MerlinController          $wizards
 */
class ComponentLoader extends DynPropertiesClass {

	use ExecOnce;
	use PluginControllerConsumer;

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( $value === null ) {
			$conClass = $this->getConsMap()[ $key ] ?? null;
			if ( !empty( $conClass ) ) {
				$value = ( $this->{$key} ?? $this->{$key} = new $conClass() );
			}
		}

		return $value;
	}

	private function getConsMap() :array {
		return [
			'activity_log'    => AuditCon::class,
			'autoupdates'     => CompCons\AutoUpdatesCon::class,
			'api_token'       => ApiTokenManager::class,
			'badge'           => PluginBadge::class,
			'bot_signals'     => BotSignalsController::class,
			'comment_spam'    => CommentSpamCon::class,
			'crowdsec'        => CrowdSecController::class,
			'file_locker'     => FileLockerController::class,
			'forms_spam'      => SpamController::class,
			'forms_users'     => UserFormsController::class,
			'http_headers'    => CompCons\HttpHeadersCon::class,
			'import_export'   => ImportExportController::class,
			'instant_alerts'  => CompCons\InstantAlertsCon::class,
			'ips_con'         => CompCons\IPsCon::class,
			'license'         => LicenseHandler::class,
			'mainwp'          => MainwpCon::class,
			'mfa'             => MfaController::class,
			'not_bot'         => NotBotHandler::class,
			'offense_tracker' => OffenseTracker::class,
			'opts_lookup'     => OptsSettingsLookup::class,
			'reports'         => ReportingController::class,
			'requests_log'    => RequestLogger::class,
			'sec_admin'       => SecurityAdminController::class,
			'session'         => SessionController::class,
			'shieldnet'       => ShieldNetApiController::class,
			'scans'           => Scan\ScansController::class,
			'scans_queue'     => Scan\Queue\Controller::class,
			'user_suspend'    => UserSuspendController::class,
			'whitelabel'      => WhitelabelController::class,
			'wizards'         => MerlinController::class,
		];
	}
}