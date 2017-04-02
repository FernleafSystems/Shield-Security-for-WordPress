{
  "slug": "login_protect",
  "properties": {
    "name": "Login Protection",
    "show_feature_menu_item": true,
    "storage_key": "loginprotect",
    "tagline": "Block brute force attacks and secure user identities with Two-Factor Authentication"
  },
  "admin_notices": {
    "email-verification-sent": {
      "once": false,
      "valid_admin": true,
      "type": "warning"
    }
  },
  "sections": [
    {
      "slug": "section_enable_plugin_feature_login_protection",
      "primary": true,
      "title": "Enable Plugin Feature: Login Protection",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - Login Protection blocks all automated and brute force attempts to log in to your site.",
        "Recommendation - Keep the Login Protection feature turned on."
      ]
    },
    {
      "slug": "section_brute_force_login_protection",
      "title": "Brute Force Login Protection",
      "title_short": "Brute Force",
      "summary": [
        "Purpose - Blocks brute force hacking attacks against your login and registration pages.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug": "section_multifactor_authentication",
      "title": "Multi-Factor Authentication",
      "title_short": "2-Factor Auth",
      "summary": [
        "Purpose - Verifies the identity of users who log in to your site - i.e. they are who they say they are.",
        "Recommendation - Use of this feature is highly recommend. However, if your host blocks email sending you may lock yourself out.",
        "Note: You may combine multiple authentication factors for increased security."
      ]
    },
    {
      "slug": "section_rename_wplogin",
      "title": "Rename WP Login Page",
      "title_short": "Rename wp-login.php",
      "summary": [
        "Purpose - To hide your wp-login.php page from brute force attacks and hacking attempts - if your login page cannot be found, no-one can login.",
        "Recommendation - This is not required for complete security and if your site has irregular or inconsistent configuration it may not work for you."
      ]
    },
    {
      "slug": "section_yubikey_authentication",
      "title": "Yubikey Authentication",
      "title_short": "Yubikey",
      "summary": [
        "Purpose - Verifies the identity of users who log in to your site - i.e. they are who they say they are.",
        "Recommendation - Use of this feature is highly recommend. Note: you must own the appropriate Yubikey hardware device."
      ]
    },
    {
      "slug": "section_bypass_login_protection",
      "title": "By-Pass Login Protection",
      "title_short": "By-Pass",
      "summary": [
        "Purpose - Compatibility with XML-RPC services such as the WordPress iPhone and Android Apps.",
        "Recommendation - Keep this turned off unless you know you need it."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_login_protect",
      "section": "section_enable_plugin_feature_login_protection",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/51",
      "link_blog": "http://icwp.io/wpsf03",
      "name": "Enable Login Protection",
      "summary": "Enable (or Disable) The Login Protection Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Login Protection feature"
    },
    {
      "key": "enable_xmlrpc_compatibility",
      "section": "section_bypass_login_protection",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "XML-RPC Compatibility",
      "summary": "Allow Login Through XML-RPC To By-Pass Login Protection Rules",
      "description": "Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App."
    },
    {
      "key": "rename_wplogin_path",
      "section": "section_rename_wplogin",
      "sensitive": true,
      "default": "",
      "type": "text",
      "link_info": "http://icwp.io/5q",
      "link_blog": "http://icwp.io/5r",
      "name": "Rename WP Login",
      "summary": "Rename The WordPress Login Page",
      "description": "Creating a path here will disable your 'wp-login.php'. Only letters and numbers are permitted: abc123"
    },
    {
      "key": "enable_chained_authentication",
      "section": "section_multifactor_authentication",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/shld7",
      "link_blog": "http://icwp.io/shld6",
      "name": "Multi-Factor Authentication",
      "summary": "Require All Active Authentication Factors",
      "description": "When enabled, all multi-factor authentication methods will be applied to a user login. Disable to only require one to pass."
    },
    {
      "key": "enable_google_authenticator",
      "section": "section_multifactor_authentication",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/shld7",
      "link_blog": "http://icwp.io/shld6",
      "name": "Enable Google Authenticator",
      "summary": "Allow Users To Use Google Authenticator",
      "description": "When enabled, users will have the option to add Google Authenticator to their WordPress user profile."
    },
    {
      "key": "enable_email_authentication",
      "section": "section_multifactor_authentication",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/3s",
      "link_blog": "",
      "name": "Enable Email Authentication",
      "summary": "Two-Factor Login Authentication By Email",
      "description": "All users will be required to verify their login by email-based two-factor authentication."
    },
    {
      "key": "two_factor_auth_user_roles",
      "section": "section_multifactor_authentication",
      "type": "multiple_select",
      "default": [
        1,
        2,
        3,
        8
      ],
      "value_options": [
        {
          "value_key": 0,
          "text": "Subscribers"
        },
        {
          "value_key": 1,
          "text": "Contributors"
        },
        {
          "value_key": 2,
          "text": "Authors"
        },
        {
          "value_key": 3,
          "text": "Editors"
        },
        {
          "value_key": 8,
          "text": "Administrators"
        }
      ],
      "link_info": "http://icwp.io/4v",
      "link_blog": "",
      "name": "Enforce - Email Authentication",
      "summary": "All User Roles Subject To Email Authentication",
      "description": "Enforces email-based authentication on all users with the selected roles. Note: This setting only applies to email authentication."
    },
    {
      "key": "enable_google_recaptcha",
      "section": "section_brute_force_login_protection",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/shld5",
      "link_blog": "",
      "name": "Google reCAPTCHA",
      "summary": "Enable Google reCAPTCHA",
      "description": "Use Google reCAPTCHA on the login screen."
    },
    {
      "key": "enable_login_gasp_check",
      "section": "section_brute_force_login_protection",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/3r",
      "link_blog": "",
      "name": "G.A.S.P Protection",
      "summary": "Use G.A.S.P. Protection To Prevent Login Attempts By Bots",
      "description": "Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON."
    },
    {
      "key": "login_limit_interval",
      "section": "section_brute_force_login_protection",
      "default": "10",
      "type": "integer",
      "link_info": "http://icwp.io/3q",
      "link_blog": "",
      "name": "Login Cooldown Interval",
      "summary": "Limit login attempts to every X seconds",
      "description": "WordPress will process only ONE login attempt for every number of seconds specified. Zero (0) turns this off."
    },
    {
      "key": "enable_user_register_checking",
      "section": "section_brute_force_login_protection",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "User Registration",
      "summary": "Apply Brute Force Protection To User Registration And Lost Passwords",
      "description": "When enabled, settings in this section will also apply to new user registration and users trying to reset passwords."
    },
    {
      "key": "enable_yubikey",
      "section": "section_yubikey_authentication",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/4f",
      "link_blog": "",
      "name": "Enable Yubikey Authentication",
      "summary": "Turn On / Off Yubikey Authentication On This Site",
      "description": "Combined with your Yubikey API Key (below) this will form the basis of your Yubikey Authentication."
    },
    {
      "key": "yubikey_app_id",
      "section": "section_yubikey_authentication",
      "sensitive": true,
      "default": "",
      "type": "text",
      "link_info": "http://icwp.io/4g",
      "link_blog": "",
      "name": "Yubikey App ID",
      "summary": "Your Unique Yubikey App ID",
      "description": "Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication."
    },
    {
      "key": "yubikey_api_key",
      "section": "section_yubikey_authentication",
      "sensitive": true,
      "default": "",
      "type": "text",
      "link_info": "http://icwp.io/4g",
      "link_blog": "",
      "name": "Yubikey API Key",
      "summary": "Your Unique Yubikey App API Key",
      "description": "Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication."
    },
    {
      "key": "yubikey_unique_keys",
      "section": "section_yubikey_authentication",
      "sensitive": true,
      "default": "",
      "type": "yubikey_unique_keys",
      "link_info": "http://icwp.io/4h",
      "link_blog": "",
      "name": "Yubikey Unique Keys",
      "summary": "Permitted 'Username - Yubikey' Pairs For This Site",
      "description": "Format: Username,Yubikey. Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey."
    },
    {
      "key": "email_can_send_verified_at",
      "transferable": false,
      "section": "section_non_ui",
      "default": -1
    },
    {
      "key": "gasp_key",
      "transferable": false,
      "sensitive": true,
      "section": "section_non_ui"
    },
    {
      "key": "two_factor_secret_key",
      "transferable": false,
      "sensitive": true,
      "section": "section_non_ui"
    },
    {
      "key": "last_login_time",
      "transferable": false,
      "section": "section_non_ui"
    },
    {
      "key": "last_login_time_file_path",
      "transferable": false,
      "sensitive": true,
      "section": "section_non_ui"
    },
    {
      "key": "two_factor_auth_table_created",
      "transferable": false,
      "section": "section_non_ui"
    },
    {
      "key": "use_login_intent_page",
      "transferable": false,
      "value": true,
      "section": "section_non_ui"
    },
    {
      "key": "current_plugin_version",
      "transferable": false,
      "section": "section_non_ui"
    }
  ],
  "definitions": {
    "two_factor_auth_table_name": "login_auth",
    "two_factor_auth_table_columns": [
      "id",
      "session_id",
      "wp_username",
      "ip",
      "pending",
      "expired_at",
      "created_at",
      "deleted_at"
    ]
  }
}