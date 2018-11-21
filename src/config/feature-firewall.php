{
  "slug":        "firewall",
  "properties":  {
    "slug":                  "firewall",
    "name":                  "Firewall",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "firewall",
    "tagline":               "Automatically block malicious URLs and data sent to your site",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   false,
    "order":                 30
  },
  "sections":    [
    {
      "slug":        "section_firewall_blocking_options",
      "primary":     true,
      "title":       "Firewall Blocking Options",
      "title_short": "Firewall Blocking",
      "summary":     [
        "Here you choose what kind of malicious data to scan for.",
        "Recommendation - Turn on as many options here as you can. If you find an incompatibility or something stops working, un-check 1 option at a time until you find the problem or review the Audit Trail."
      ]
    },
    {
      "slug":        "section_choose_firewall_block_response",
      "title":       "Choose Firewall Block Response",
      "title_short": "Firewall Response",
      "summary":     [
        "Here you choose how the plugin will respond when it detects malicious data.",
        "Recommendation - Choose the option 'Die With Message'."
      ]
    },
    {
      "slug":        "section_whitelist",
      "title":       "Whitelists - IPs, Pages, Parameters, and Users that by-pass the Firewall",
      "title_short": "Whitelist",
      "summary":     [
        "In principle you should not need to whitelist anything or anyone unless you have discovered a collision with another plugin.",
        "Recommendation - Do not whitelist anything unless you are confident in what you are doing."
      ]
    },
    {
      "slug":        "section_user_messages",
      "title":       "Customize Messages Shown To User",
      "title_short": "Visitor Messages",
      "summary":     [
        "Purpose - Customize the messages shown to visitors.",
        "Recommendation - Be sure to change the messages to suit your audience.",
        "Hint - To reset any message to its default, enter the text exactly: default"
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_wordpress_firewall",
      "title":       "Enable Module: Firewall",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - The Firewall is designed to analyse data sent to your website and block any requests that appear to be malicious.",
        "Recommendation - Keep the Firewall feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_firewall",
      "section":     "section_enable_plugin_feature_wordpress_firewall",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/43",
      "link_blog":   "https://icwp.io/wpsf01",
      "name":        "Enable Firewall",
      "summary":     "Enable (or Disable) The Firewall module",
      "description": "Un-Checking this option will completely disable the Firewall module"
    },
    {
      "key":         "include_cookie_checks",
      "section":     "section_firewall_blocking_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Include Cookies",
      "summary":     "Also Test Cookie Values In Firewall Tests",
      "description": "The firewall tests GET and POST, but with this option checked it will also check COOKIE values."
    },
    {
      "key":         "block_dir_traversal",
      "section":     "section_firewall_blocking_options",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Directory Traversals",
      "summary":     "Block Directory Traversals",
      "description": "This will block directory traversal paths in in application parameters."
    },
    {
      "key":         "block_sql_queries",
      "section":     "section_firewall_blocking_options",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "SQL Queries",
      "summary":     "Block SQL Queries",
      "description": "This will block SQL in application parameters."
    },
    {
      "key":         "block_wordpress_terms",
      "section":     "section_firewall_blocking_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "WordPress Terms",
      "summary":     "Block WordPress Specific Terms",
      "description": "This will block WordPress specific terms in application parameters (wp_, user_login, etc.)."
    },
    {
      "key":         "block_field_truncation",
      "section":     "section_firewall_blocking_options",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Field Truncation",
      "summary":     "Block Field Truncation Attacks",
      "description": "This will block field truncation attacks in application parameters"
    },
    {
      "key":         "block_php_code",
      "section":     "section_firewall_blocking_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "PHP Code",
      "summary":     "Block PHP Code Includes",
      "description": "This will block any data that appears to try and include PHP files. Will probably block saving within the Plugin/Theme file editors."
    },
    {
      "key":         "block_exe_file_uploads",
      "section":     "section_firewall_blocking_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Exe File Uploads",
      "summary":     "Block Executable File Uploads",
      "description": "This will block executable file uploads (.php, .exe, etc.)."
    },
    {
      "key":         "block_leading_schema",
      "section":     "section_firewall_blocking_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Leading Schemas",
      "summary":     "Block Leading Schemas (HTTPS / HTTP)",
      "description": "This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with other plugins)."
    },
    {
      "key":         "block_aggressive",
      "section":     "section_firewall_blocking_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Aggressive Scan",
      "summary":     "Aggressively Block Data",
      "description": "Employs a set of aggressive rules to detect and block malicious data submitted to your site. Warning - May cause an increase in false-positive firewall blocks."
    },
    {
      "key":           "block_response",
      "section":       "section_choose_firewall_block_response",
      "default":       "redirect_die_message",
      "type":          "select",
      "value_options": [
        {
          "value_key": "redirect_die_message",
          "text":      "Die With Message"
        },
        {
          "value_key": "redirect_die",
          "text":      "Die"
        },
        {
          "value_key": "redirect_home",
          "text":      "Redirect To Home Page"
        },
        {
          "value_key": "redirect_404",
          "text":      "Return 404"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Block Response",
      "summary":       "How the firewall responds when it blocks a request",
      "description":   "We recommend dying with a message so you know what might have occurred when the firewall blocks you."
    },
    {
      "key":         "block_send_email",
      "section":     "section_choose_firewall_block_response",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Send Email Report",
      "summary":     "When a visitor is blocked the firewall will send an email to the configured email address",
      "description": "Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host."
    },
    {
      "key":         "page_params_whitelist",
      "section":     "section_whitelist",
      "default":     "",
      "type":        "comma_separated_lists",
      "link_info":   "https://icwp.io/2a",
      "link_blog":   "",
      "name":        "Whitelist Parameters",
      "summary":     "Detail pages and parameters that are whitelisted (ignored by the firewall)",
      "description": "This should be used with caution and you should only provide parameter names that you must have excluded"
    },
    {
      "key":         "whitelist_admins",
      "section":     "section_whitelist",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Ignore Administrators",
      "summary":     "Ignore Administrators",
      "description": "Authenticated administrator users will not be processed by the firewall rules."
    },
    {
      "key":         "text_firewalldie",
      "section":     "section_user_messages",
      "sensitive":   true,
      "premium":     true,
      "default":     "default",
      "type":        "text",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Firewall Block Message",
      "summary":     "Message Displayed To Visitor When A Firewall Block Is Triggered",
      "description": "When you select the option to display a message to the visitor, this is the message that is displayed."
    },
    {
      "key":          "insights_last_firewall_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    }
  ],
  "definitions": {
    "default_whitelist": {
      "/wp-admin/options-general.php": [],
      "/wp-admin/options.php":         [
        "home",
        "siteurl"
      ],
      "/wp-admin/post-new.php":        [],
      "/wp-admin/page-new.php":        [],
      "/wp-admin/link-add.php":        [],
      "/wp-admin/media-upload.php":    [],
      "/wp-admin/post.php":            [
        "content"
      ],
      "/wp-admin/plugin-editor.php":   [
        "newcontent"
      ],
      "/wp-admin/page.php":            [],
      "/wp-admin/admin-ajax.php":      [],
      "/wp-comments-post.php":         [
        "url",
        "comment"
      ],
      "*":                             [
        "ajaxurl",
        "g-recaptcha-response",
        "verify_sign",
        "txn_id",
        "wp_http_referer",
        "_wp_http_referer",
        "_wp_original_http_referer",
        "pwd",
        "url",
        "referredby",
        "redirect_to",
        "jetpack_sso_original_request",
        "jetpack_sso_redirect_to",
        "/^wordpress_logged_in_[0-9a-f]+$/",
        "edd_action",
        "edd_redirect"
      ]
    },
    "firewall_patterns": {
      "dirtraversal":    {
        "simple": [
          "etc/passwd",
          "proc/self/environ",
          "etc/passwd",
          "makefile",
          "wwwroot",
          "pingserver",
          "../",
          "loopback"
        ]
      },
      "wpterms":         {
        "simple": [
          "/**/",
          "wp-config.php"
        ],
        "regex":  [
          "^wp_",
          "^user_login",
          "^user_pass",
          "[^0-9]0x[0-9a-f][0-9a-f]"
        ]
      },
      "fieldtruncation": {
        "regex": [
          "\\s{49,}",
          "\\x00"
        ]
      },
      "sqlqueries":      {
        "regex": [
          "concat\\s*\\(",
          "group_concat",
          "union.*select"
        ]
      },
      "exefile":         {
        "regex": [
          "\\.(dll|rb|py|exe|php[3-6]?|pl|perl|ph[34]|phl|phtml|phtm|sql|ini|jsp|asp|git|svn|tar)$"
        ]
      },
      "schema":          {
        "simple": [
          ".shtml"
        ],
        "regex":  [
          "^(http|https|ftp|file):"
        ]
      },
      "phpcode":         {
        "simple": null,
        "regex":  [
          "(include|include_once|require|require_once)\\s*\\(.*\\)"
        ]
      },
      "aggressive":      {
        "simple": [
          "eval(",
          "(null)",
          "base64_",
          "localhost",
          "(function(",
          "{x.html(",
          ").html(",
          "...",
          "/httpdocs/",
          "/tmp/",
          "boot.ini"
        ],
        "regex":  [
          "GLOBALS(=|\\[|%%)",
          "REQUEST(=|\\[|%%)",
          "(`|\\<|\\>|\\[|\\]|\\{|\\}|\\?)",
          "drop\\s+table\\s+(`|'?)[a-z0-9]+\\1",
          "'\\s+OR\\s+'([a-z0-9]+)'\\s*=\\s*'\\1'\\s+(--|\\(\\{|\\/\\*)\\s+"
        ]
      }
    }
  }
}