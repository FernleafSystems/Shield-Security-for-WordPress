<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

/**
 * Class UserMeta
 * @package FernleafSystems\Wordpress\Plugin\Shield\Users
 * @property string $email_secret
 * @property bool   $email_validated
 * @property string $backupcode_secret
 * @property string $backupcode_validated
 * @property string $ga_secret
 * @property bool   $ga_validated
 * @property array  $hash_loginmfa
 * @property string $pass_hash
 * @property int    $pass_started_at
 * @property int    $pass_reset_last_redirect_at
 * @property int    $pass_check_failed_at
 * @property string $yubi_secret
 * @property bool   $yubi_validated
 * @property int    $last_login_at
 * @property bool   $wc_social_login_valid
 */
class ShieldUserMeta extends \FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta {

}