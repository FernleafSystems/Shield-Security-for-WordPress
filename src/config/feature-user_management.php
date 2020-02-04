{
  "slug":        "user_management",
  "properties":  {
    "name":                  "User Management",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "user_management",
    "tagline":               "Control user sessions, duration, timeouts and account sharing",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   false,
    "run_if_wpcli":          false,
    "order":                 40
  },
  "sections":    [
    {
      "slug":        "section_user_session_management",
      "primary":     true,
      "title":       "User Session Management",
      "title_short": "Session Options",
      "summary":     [
        "Purpose - Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_user_reg",
      "title":       "User Registration",
      "title_short": "User Registration",
      "summary":     [
        "Purpose - Control user registration and prevent SPAM.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_passwords",
      "reqs":        {
        "wp_min": "4.4"
      },
      "title":       "Password Policies",
      "title_short": "Password Policies",
      "summary":     [
        "Purpose - Have full control over passwords used by users on the site.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_suspend",
      "title":       "Automatic And Manual User Suspension",
      "title_short": "User Suspension",
      "summary":     [
        "Purpose - Automatically suspend accounts to prevent login by certain users.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_admin_login_notification",
      "title":       "Admin Login Notification",
      "title_short": "Notifications",
      "summary":     [
        "Purpose - So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_user_accounts_management",
      "title":       "Enable Module: User Management",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.",
        "Recommendation - Keep the User Management feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_user_management",
      "section":     "section_enable_plugin_feature_user_accounts_management",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/e3",
      "link_blog":   "",
      "name":        "Enable User Management",
      "summary":     "Enable (or Disable) The User Management module",
      "description": "Un-Checking this option will completely disable the User Management module"
    },
    {
      "key":         "enable_user_login_email_notification",
      "section":     "section_admin_login_notification",
      "premium":     true,
      "sensitive":   false,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/e2",
      "link_blog":   "",
      "name":        "User Login Notification Email",
      "summary":     "Send Email Notification To Each User Upon Successful Login",
      "description": "A notification is sent to each user when a successful login occurs for their account."
    },
    {
      "key":         "enable_admin_login_email_notification",
      "section":     "section_admin_login_notification",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Admin Login Notification Email",
      "summary":     "Send An Notification Email When Administrator Logs In",
      "description": "If you would like to be notified every time an administrator user logs into this WordPress site, enter a notification email address. No email address - No Notification."
    },
    {
      "key":         "session_timeout_interval",
      "section":     "section_user_session_management",
      "default":     2,
      "min":         0,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Session Timeout",
      "summary":     "Specify How Many Days After Login To Automatically Force Re-Login",
      "description": "WordPress default is 2 days, or 14 days if you check the 'Remember Me' box."
    },
    {
      "key":         "session_idle_timeout_interval",
      "section":     "section_user_session_management",
      "default":     48,
      "min":         0,
      "type":        "integer",
      "link_info":   "https://icontrolwp.freshdesk.com/support/solutions/articles/3000070590",
      "link_blog":   "",
      "name":        "Idle Timeout",
      "summary":     "Specify How Many Hours After Inactivity To Automatically Logout User",
      "description": "If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return. Set this to '0' to turn off this option."
    },
    {
      "key":         "session_lock_location",
      "section":     "section_user_session_management",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Lock To Location",
      "summary":     "Locks A User Session To IP address",
      "description": "When selected, a session is restricted to the same IP address as when the user logged in. If a logged-in user's IP address changes, the session will be invalidated and they'll be forced to re-login to WordPress."
    },
    {
      "key":         "session_username_concurrent_limit",
      "section":     "section_user_session_management",
      "default":     0,
      "min":         0,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Max Simultaneous Sessions",
      "summary":     "Limit Simultaneous Sessions For The Same Username",
      "description": "The number provided here is the maximum number of simultaneous, distinct, sessions allowed for any given username. Use '0' for no limits."
    },
    {
      "key":           "enable_email_validate",
      "section":       "section_user_reg",
      "premium":       true,
      "type":          "select",
      "default":       "disabled",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Log Only"
        },
        {
          "value_key": "kill",
          "text":      "Kill Connection"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Validate Email Addresses",
      "summary":       "Validate Email Addresses When User Attempts To Register",
      "description":   "Validate Email Addresses When User Attempts To Register."
    },
    {
      "key":           "email_checks",
      "section":       "section_user_reg",
      "type":          "multiple_select",
      "default":       [ "syntax", "domain" ],
      "value_options": [
        {
          "value_key": "syntax",
          "text":      "Email Address Syntax"
        },
        {
          "value_key": "domain",
          "text":      "Domain Name Resolves"
        },
        {
          "value_key": "mx",
          "text":      "Domain MX"
        },
        {
          "value_key": "nondisposable",
          "text":      "Disposable Email Service"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Email Checks",
      "summary":       "The Email Address Properties That Will Be Tested",
      "description":   "Select which ."
    },
    {
      "key":         "enable_password_policies",
      "section":     "section_passwords",
      "type":        "checkbox",
      "default":     "N",
      "link_info":   "https://shsec.io/e1",
      "link_blog":   "https://shsec.io/c4",
      "name":        "Enable Password Policies",
      "summary":     "Enable The Password Policies Below",
      "description": "Turn on/off all password policies."
    },
    {
      "key":         "pass_prevent_pwned",
      "section":     "section_passwords",
      "type":        "checkbox",
      "default":     "Y",
      "link_info":   "https://shsec.io/by",
      "link_blog":   "",
      "name":        "Prevent Pwned Passwords",
      "summary":     "Prevent Use Of Pwned Passwords",
      "description": "Prevents users from using any passwords found on the public available list of pwned passwords."
    },
    {
      "key":         "pass_min_length",
      "section":     "section_passwords",
      "premium":     true,
      "type":        "integer",
      "default":     "12",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Minimum Length",
      "summary":     "Minimum Password Length",
      "description": "All passwords that a user sets must be at least this many characters in length."
    },
    {
      "key":           "pass_min_strength",
      "section":       "section_passwords",
      "premium":       true,
      "type":          "select",
      "default":       "4",
      "value_options": [
        {
          "value_key": "0",
          "text":      "Very Weak"
        },
        {
          "value_key": "1",
          "text":      "Weak"
        },
        {
          "value_key": "2",
          "text":      "Medium"
        },
        {
          "value_key": "3",
          "text":      "Strong"
        },
        {
          "value_key": "4",
          "text":      "Very Strong"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Minimum Strength",
      "summary":       "Minimum Password Strength",
      "description":   "All passwords that a user sets must meet this minimum strength."
    },
    {
      "key":         "pass_force_existing",
      "section":     "section_passwords",
      "premium":     true,
      "type":        "checkbox",
      "default":     "N",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Apply To Existing Users",
      "summary":     "Apply Password Policies To Existing Users and Their Passwords",
      "description": "Forces existing users to update their passwords if they don't meet requirements, after they next login ."
    },
    {
      "key":         "pass_expire",
      "section":     "section_passwords",
      "premium":     true,
      "type":        "integer",
      "default":     "60",
      "min":         0,
      "link_info":   "",
      "link_blog":   "",
      "name":        "Password Expiration",
      "summary":     "Passwords Expire After This Many Days",
      "description": "Users will be forced to reset their passwords after the number of days specified."
    },
    {
      "key":         "manual_suspend",
      "section":     "section_suspend",
      "premium":     true,
      "type":        "checkbox",
      "default":     "N",
      "link_info":   "https://shsec.io/fq",
      "link_blog":   "https://shsec.io/fr",
      "name":        "Allow Manual User Suspension",
      "summary":     "Manually Suspend User Accounts To Prevent Login",
      "description": "Users may be suspended by administrators to prevent login."
    },
    {
      "key":         "auto_password",
      "section":     "section_suspend",
      "premium":     true,
      "type":        "checkbox",
      "default":     "Y",
      "link_info":   "https://shsec.io/fs",
      "link_blog":   "https://shsec.io/fr",
      "name":        "Auto-Suspend Expired Passwords",
      "summary":     "Automatically Suspend Users With Expired Passwords",
      "description": "Suspend login by users and require password reset to unsuspend."
    },
    {
      "key":         "auto_idle_days",
      "section":     "section_suspend",
      "premium":     true,
      "type":        "integer",
      "default":     0,
      "min":         0,
      "link_info":   "https://shsec.io/ft",
      "link_blog":   "https://shsec.io/fr",
      "name":        "Auto-Suspend Idle Users",
      "summary":     "Automatically Suspend Idle User Accounts",
      "description": "Prevent login by idle users and require password reset to unsuspend."
    },
    {
      "key":         "auto_idle_roles",
      "section":     "section_suspend",
      "premium":     true,
      "type":        "array",
      "default":     [
        "administrator",
        "editor",
        "author"
      ],
      "link_info":   "https://shsec.io/ft",
      "link_blog":   "",
      "name":        "Auto-Suspend Idle Users",
      "summary":     "Automatically Suspend Idle User Accounts",
      "description": "Prevent login by idle users and require password reset to unsuspend."
    },
    {
      "key":          "autoadd_sessions_started_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "hard_suspended_userids",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    }
  ],
  "definitions": {
    "pwned_api_url_password_single": "https://api.pwnedpasswords.com/pwnedpassword/",
    "pwned_api_url_password_range":  "https://api.pwnedpasswords.com/range/",
    "events":                        {
      "session_notfound":             {
      },
      "session_expired":              {
      },
      "session_idle":                 {
      },
      "session_iplock":               {
      },
      "session_browserlock":          {
      },
      "session_unverified":           {
      },
      "password_expired":             {
      },
      "password_policy_force_change": {
        "recent": true
      },
      "password_policy_block":        {
        "recent": true
      },
      "user_hard_suspended":          {
        "recent": true
      },
      "user_hard_unsuspended":        {
      },
      "reg_email_invalid":            {
        "offense": true
      }
    }
  }
}