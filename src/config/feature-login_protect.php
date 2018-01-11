{
  "slug": "login_protect",
  "properties": {
    "slug": "login_protect",
    "name": "Login Protection",
    "show_feature_menu_item": true,
    "storage_key": "loginprotect",
    "tagline": "Block brute force attacks and secure user identities with Two-Factor Authentication",
    "show_central": true,
    "access_restricted": true,
    "premium": false,
    "order": 40
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
      "slug": "section_recaptcha",
      "title": "Google reCAPTCHA",
      "title_short": "reCAPTCHA",
      "summary": [
        "Purpose - Adds Google reCAPTCHA to the Login Forms.",
        "Recommendation - Keep this turned on.",
        "Note - You will need to register for Google reCAPTCHA keys and store them in the Shield 'Dashboard' settings."
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
      "slug": "section_user_messages",
      "title": "User Messages",
      "title_short": "User Messages",
      "summary": [
        "Purpose - Customize the messages shown to visitors.",
        "Recommendation - Be sure to change the messages to suit your audience.",
        "Hint - To reset any message to its default, enter the text exactly: default"
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
      "key": "login_protect_3pty",
      "section": "section_enable_plugin_feature_login_protection",
      "premium": true,
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "3rd-Party Support",
      "summary": "Support For 3rd-Party Plugins, e.g. WooCommerce",
      "description": "Add Support For 3rd-Party Login, Register, and Password Reset Forms e.g. WooCommerce, Easy Digital Downloads."
    },
    {
      "key": "enable_xmlrpc_compatibility",
      "section": "section_enable_plugin_feature_login_protection",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/9u",
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
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/9r",
      "link_blog": "http://icwp.io/84",
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
      "link_info": "http://icwp.io/3t",
      "link_blog": "http://icwp.io/9q",
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
      "key": "enable_google_recaptcha_login",
      "section": "section_recaptcha",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/9m",
      "link_blog": "http://icwp.io/shld5",
      "name": "Google reCAPTCHA",
      "summary": "Enable Google reCAPTCHA",
      "description": "Use Google reCAPTCHA on the login screen."
    },
    {
      "key": "google_recaptcha_style_login",
      "section": "section_recaptcha",
      "premium": true,
      "default": "default",
      "type": "select",
      "value_options": [
        {
          "value_key": "default",
          "text": "Default"
        },
        {
          "value_key": "light",
          "text": "Light Theme"
        },
        {
          "value_key": "dark",
          "text": "Dark Theme"
        },
        {
          "value_key": "invisible",
          "text": "Invisible reCAPTCHA"
        }
      ],
      "link_info": "",
      "link_blog": "",
      "name": "reCAPTCHA Style",
      "summary": "How Google reCAPTCHA Will Be Displayed",
      "description": "You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha."
    },
    {
      "key": "enable_login_gasp_check",
      "section": "section_brute_force_login_protection",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/3r",
      "link_blog": "http://icwp.io/9n",
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
      "link_blog": "http://icwp.io/9o",
      "name": "Login Cooldown Interval",
      "summary": "Limit login attempts to every X seconds",
      "description": "WordPress will process only ONE login attempt for every number of seconds specified. Zero (0) turns this off."
    },
    {
      "key": "enable_user_register_checking",
      "section": "section_brute_force_login_protection",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/9p",
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
      "link_blog": "http://icwp.io/9t",
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
	   "key": "text_imahuman",
	   "section": "section_user_messages",
	   "premium": true,
	   "default": "default",
	   "type": "text",
	   "link_info": "",
	   "link_blog": "",
	   "name": "GASP Checkbox Text",
	   "summary": "The Message Displayed Next To The GASP Checkbox",
	   "description": "You can change the text displayed to the user beside the checkbox if you need a customized message."
	},
	{
	   "key": "text_pleasecheckbox",
	   "section": "section_user_messages",
	   "premium": true,
	   "default": "default",
	   "type": "text",
	   "link_info": "",
	   "link_blog": "",
	   "name": "GASP Alert Text",
	   "summary": "The Message Displayed If The User Doesn't Check The Box",
	   "description": "You can change the text displayed to the user in the alert message if they don't check the box."
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
      "key": "two_factor_auth_table_created",
      "transferable": false,
      "section": "section_non_ui"
    },
    {
      "key": "use_login_intent_page",
      "transferable": false,
      "value": true,
      "section": "section_non_ui"
    }
  ],
  "definitions": {
    "login_intent_timeout": 5,
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
    ],
    "wizards": {
      "mfa": {
  	    "min_user_permissions": "manage_options",
  	    "steps": {
		  "start": {
		    "restricted_access": false,
		    "title": "Start: Unrecognised File Scanner"
		  },
		  "authemail": {
		    "title": "Email Authentication"
		  },
		  "authga": {
		    "title": "Google Authenticator"
		  },
		  "multiselect": {
		    "title": "Select Multifactor Auth"
		  },
		  "finished": {
		    "restricted_access": false,
		    "title": "Finished: Unrecognised File Scanner"
		  }
  	    }
      }
    }
  }
}