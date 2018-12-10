{
  "slug":          "login_protect",
  "properties":    {
    "slug":                   "login_protect",
    "name":                   "Login Guard",
    "show_module_menu_item":  false,
    "show_module_options":    true,
    "storage_key":            "loginprotect",
    "tagline":                "Block brute force attacks and secure user identities with Two-Factor Authentication",
    "show_central":           true,
    "access_restricted":      true,
    "premium":                false,
    "run_if_whitelisted":     false,
    "run_if_verified_bot":    false,
    "order":                  40
  },
  "admin_notices": {
    "email-verification-sent": {
      "once":        false,
      "valid_admin": true,
      "type":        "warning"
    }
  },
  "sections":      [
    {
      "slug":        "section_brute_force_login_protection",
      "primary":     true,
      "title":       "Brute Force Login Guard",
      "title_short": "Brute Force",
      "summary":     [
        "Purpose - Blocks brute force hacking attacks against your login and registration pages.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_recaptcha",
      "title":       "Google reCAPTCHA",
      "title_short": "reCAPTCHA",
      "summary":     [
        "Purpose - Adds Google reCAPTCHA to the Login Forms.",
        "Recommendation - Keep this turned on.",
        "Note - You will need to register for Google reCAPTCHA keys and store them in the Shield 'Dashboard' settings."
      ]
    },
    {
      "slug":        "section_2fa_email",
      "title":       "Email Two-Factor Authentication",
      "title_short": "2FA - Email",
      "summary":     [
        "Purpose - Verifies the identity of users who log in to your site using email-based one-time-passwords.",
        "Recommendation - Use of this feature is highly recommend. However, if your host blocks email sending you may lock yourself out.",
        "Note: You may combine multiple authentication factors for increased security."
      ]
    },
    {
      "slug":        "section_2fa_ga",
      "title":       "Google Authenticator Two-Factor Authentication",
      "title_short": "2FA - Google Authenticator",
      "summary":     [
        "Purpose - Verifies the identity of users who log in to your site using Google Authenticator one-time-passwords.",
        "Recommendation - Use of this feature is highly recommend. However, if your host blocks email sending you may lock yourself out.",
        "Note: You may combine multiple authentication factors for increased security."
      ]
    },
    {
      "slug":        "section_yubikey_authentication",
      "title":       "Yubikey Two-Factor Authentication",
      "title_short": "2FA -Yubikey",
      "summary":     [
        "Purpose - Verifies the identity of users who log in to your site using Yubikey one-time-passwords.",
        "Note: You may combine multiple authentication factors for increased security."
      ]
    },
    {
      "slug":        "section_multifactor_authentication",
      "title":       "Multi-Factor Authentication",
      "title_short": "2-Factor Auth",
      "summary":     [
        "Purpose - Verifies the identity of users who log in to your site - i.e. they are who they say they are.",
        "Recommendation - Use of this feature is highly recommend. However, if your host blocks email sending you may lock yourself out.",
        "Note: You may combine multiple authentication factors for increased security."
      ]
    },
    {
      "slug":        "section_rename_wplogin",
      "title":       "Hide WP Login Page",
      "title_short": "Hide Login Page",
      "summary":     [
        "Purpose - To hide your wp-login.php page from brute force attacks and hacking attempts - if your login page cannot be found, no-one can login.",
        "Recommendation - This is not required for complete security and if your site has irregular or inconsistent configuration it may not work for you."
      ]
    },
    {
      "slug":        "section_user_messages",
      "title":       "User Messages",
      "title_short": "User Messages",
      "summary":     [
        "Purpose - Customize the messages shown to visitors.",
        "Recommendation - Be sure to change the messages to suit your audience.",
        "Hint - To reset any message to its default, enter the text exactly: default"
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_login_protection",
      "title":       "Disable Login Guard Module",
      "title_short": "Disable",
      "summary":     [
        "Purpose - Login Guard blocks all automated and brute force attempts to log in to your site.",
        "Recommendation - Keep the Login Guard module turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":       [
    {
      "key":         "enable_login_protect",
      "section":     "section_enable_plugin_feature_login_protection",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/51",
      "link_blog":   "https://icwp.io/wpsf03",
      "name":        "Enable Login Guard",
      "summary":     "Enable (or Disable) The Login Guard Module",
      "description": "Un-Checking this option will completely disable the Login Guard module"
    },
    {
      "key":         "rename_wplogin_path",
      "section":     "section_rename_wplogin",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://icwp.io/5q",
      "link_blog":   "https://icwp.io/5r",
      "name":        "Hide Login Page",
      "summary":     "Rename The WordPress Login Page",
      "description": "Creating a path here will disable your 'wp-login.php'. Only letters and numbers are permitted: abc123"
    },
    {
      "key":         "enable_chained_authentication",
      "section":     "section_multifactor_authentication",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/9r",
      "link_blog":   "https://icwp.io/84",
      "name":        "Multi-Factor Authentication",
      "summary":     "Require All Active Authentication Factors",
      "description": "When enabled, all multi-factor authentication methods will be applied to a user login. Disable to only require one to pass."
    },
    {
      "key":         "mfa_skip",
      "section":     "section_multifactor_authentication",
      "premium":     true,
      "default":     0,
      "min":         0,
      "type":        "integer",
      "link_info":   "https://icwp.io/b1",
      "link_blog":   "",
      "name":        "Multi-Factor By-Pass",
      "summary":     "A User Can By-Pass Multi-Factor Authentication (MFA) For The Set Number Of Days",
      "description": "Enter the number of days a user can by-pass future MFA after a successful MFA-login. 0 to disable."
    },
    {
      "key":         "allow_backupcodes",
      "section":     "section_multifactor_authentication",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/dx",
      "link_blog":   "https://icwp.io/dy",
      "name":        "Allow Backup Codes",
      "summary":     "Allow Users To Generate A Backup Code",
      "description": "Allow users to generate a backup code that can be used to login if MFA factors are unavailable."
    },
    {
      "key":         "enable_google_authenticator",
      "section":     "section_2fa_ga",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/shld7",
      "link_blog":   "https://icwp.io/shld6",
      "name":        "Enable Google Authenticator",
      "summary":     "Allow Users To Use Google Authenticator",
      "description": "When enabled, users will have the option to add Google Authenticator to their WordPress user profile."
    },
    {
      "key":         "enable_email_authentication",
      "section":     "section_2fa_email",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/3t",
      "link_blog":   "https://icwp.io/9q",
      "name":        "Enable Email Authentication",
      "summary":     "Two-Factor Login Authentication By Email",
      "description": "All users will be required to verify their login by email-based two-factor authentication."
    },
    {
      "key":           "two_factor_auth_user_roles",
      "section":       "section_2fa_email",
      "type":          "multiple_select",
      "default":       [
        "contributor",
        "author",
        "editor",
        "administrator"
      ],
      "value_options": [
        {
          "value_key": "subscriber",
          "text":      "Subscribers"
        },
        {
          "value_key": "contributor",
          "text":      "Contributors"
        },
        {
          "value_key": "author",
          "text":      "Authors"
        },
        {
          "value_key": "editor",
          "text":      "Editors"
        },
        {
          "value_key": "administrator",
          "text":      "Administrators"
        },
        {
          "value_key": "customer",
          "text":      "[Woo] Customer"
        },
        {
          "value_key": "shop_manager",
          "text":      "[Woo/EDD] Shop Manager"
        },
        {
          "value_key": "shop_accountant",
          "text":      "[EDD] Shop Accountant"
        },
        {
          "value_key": "shop_worker",
          "text":      "[EDD] Shop Worker"
        },
        {
          "value_key": "edd_subscriber",
          "text":      "[EDD] Customer"
        }
      ],
      "link_info":     "https://icwp.io/4v",
      "link_blog":     "",
      "name":          "Enforce - Email Authentication",
      "summary":       "All User Roles Subject To Email Authentication",
      "description":   "Enforces email-based authentication on all users with the selected roles. Note: This setting only applies to email authentication."
    },
    {
      "key":           "bot_protection_locations",
      "section":       "section_brute_force_login_protection",
      "type":          "multiple_select",
      "default":       [ "login" ],
      "value_options": [
        {
          "value_key": "login",
          "text":      "Login"
        },
        {
          "value_key": "register",
          "text":      "Register"
        },
        {
          "value_key": "password",
          "text":      "Lost Password"
        },
        {
          "value_key": "checkout_woo",
          "text":      "Checkout (WooCommerce)"
        }
      ],
      "link_info":     "https://icwp.io/dv",
      "link_blog":     "",
      "name":          "Protection Locations",
      "summary":       "How Google reCAPTCHA Will Be Displayed",
      "description":   "Choose for which forms bot protection measures will be deployed."
    },
    {
      "key":         "login_limit_interval",
      "section":     "section_brute_force_login_protection",
      "default":     "10",
      "type":        "integer",
      "link_info":   "https://icwp.io/3q",
      "link_blog":   "https://icwp.io/9o",
      "name":        "Login Cooldown Interval",
      "summary":     "Limit login attempts to every X seconds",
      "description": "WordPress will process only ONE login attempt for every number of seconds specified. Zero (0) turns this off."
    },
    {
      "key":         "enable_login_gasp_check",
      "section":     "section_brute_force_login_protection",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/3r",
      "link_blog":   "https://icwp.io/9n",
      "name":        "Bot Protection",
      "summary":     "Protect WP Login From Automated Login Attempts By Bots",
      "description": "Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON."
    },
    {
      "key":         "enable_google_recaptcha_login",
      "section":     "section_brute_force_login_protection",
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "default",
          "text":      "Default Style"
        },
        {
          "value_key": "light",
          "text":      "Light Theme"
        },
        {
          "value_key": "dark",
          "text":      "Dark Theme"
        },
        {
          "value_key": "invisible",
          "text":      "Invisible reCAPTCHA"
        }
      ],
      "link_info":   "https://icwp.io/9m",
      "link_blog":   "https://icwp.io/shld5",
      "name":        "Google reCAPTCHA",
      "summary":     "Enable Google reCAPTCHA",
      "description": "Use Google reCAPTCHA on the login screen."
    },
    {
      "key":         "enable_antibot_js",
      "section":     "section_brute_force_login_protection",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/dw",
      "link_blog":   "",
      "name":        "AntiBot JS",
      "summary":     "Load Anti-Bot JS For 3rd Party Login Forms",
      "description": "Important: This is experimental. Please contact support for further assistance."
    },
    {
      "key":         "antibot_form_ids",
      "section":     "section_brute_force_login_protection",
      "type":        "array",
      "default":     [
      	"form#ihc_login_form",
      	"form#createuser"
      ],
      "link_info":   "",
      "link_blog":   "",
      "name":        "AntiBot Forms",
      "summary":     "Enter The IDs Of The 3rd Party Login Forms For Use With AntiBot JS",
      "description": "For Use With AnitBot JS (above)."
    },
    {
      "key":         "enable_yubikey",
      "section":     "section_yubikey_authentication",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/4f",
      "link_blog":   "https://icwp.io/9t",
      "name":        "Enable Yubikey Authentication",
      "summary":     "Turn On / Off Yubikey Authentication On This Site",
      "description": "Combined with your Yubikey API Key (below) this will form the basis of your Yubikey Authentication."
    },
    {
      "key":         "yubikey_app_id",
      "section":     "section_yubikey_authentication",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://icwp.io/4g",
      "link_blog":   "",
      "name":        "Yubikey App ID",
      "summary":     "Your Unique Yubikey App ID",
      "description": "Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication."
    },
    {
      "key":         "yubikey_api_key",
      "section":     "section_yubikey_authentication",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://icwp.io/4g",
      "link_blog":   "",
      "name":        "Yubikey API Key",
      "summary":     "Your Unique Yubikey App API Key",
      "description": "Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication."
    },
    {
      "key":         "text_imahuman",
      "section":     "section_user_messages",
      "sensitive":   true,
      "premium":     true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://icwp.io/dz",
      "link_blog":   "",
      "name":        "GASP Checkbox Text",
      "summary":     "The Message Displayed Next To The GASP Checkbox",
      "description": "You can change the text displayed to the user beside the checkbox if you need a customized message."
    },
    {
      "key":         "text_pleasecheckbox",
      "section":     "section_user_messages",
      "sensitive":   true,
      "premium":     true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://icwp.io/dz",
      "link_blog":   "",
      "name":        "GASP Alert Text",
      "summary":     "The Message Displayed If The User Doesn't Check The Box",
      "description": "You can change the text displayed to the user in the alert message if they don't check the box."
    },
    {
      "key":          "email_can_send_verified_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      -1
    },
    {
      "key":          "gasp_key",
      "transferable": false,
      "sensitive":    true,
      "section":      "section_non_ui"
    },
    {
      "key":          "two_factor_secret_key",
      "transferable": false,
      "sensitive":    true,
      "section":      "section_non_ui"
    },
    {
      "key":          "two_factor_auth_table_created",
      "transferable": false,
      "section":      "section_non_ui"
    },
    {
      "key":          "use_login_intent_page",
      "transferable": false,
      "value":        true,
      "section":      "section_non_ui"
    },
    {
      "key":          "insights_last_2fa_login_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    },
    {
      "key":          "insights_last_login_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    },
    {
      "key":          "insights_last_register_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    },
    {
      "key":          "insights_last_reset-password_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    }
  ],
  "definitions":   {
    "login_intent_timeout":          5,
    "wizards":                       {
      "mfa": {
        "title": "Configure Multi-Factor Login Authentication",
        "desc": "Easily setup multi-factor login authentication, using Email and Google Authenticator.",
        "min_user_permissions": "manage_options",
        "steps":                {
          "start":       {
            "security_admin": false,
            "title":             "Start: Multi-Factor Authentication"
          },
          "authemail":   {
            "title": "Email Authentication"
          },
          "authga":      {
            "title": "Google Authenticator"
          },
          "multiselect": {
            "title": "Select Multifactor Auth"
          },
          "finished":    {
            "security_admin": false,
            "title":             "Finished: Multi-Factor Authentication"
          }
        }
      }
    }
  }
}