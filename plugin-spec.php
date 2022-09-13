{
  "properties":       {
    "version":                 "16.1.3",
    "release_timestamp":       1663064000,
    "build":                   "202209.1302",
    "slug_parent":             "icwp",
    "slug_plugin":             "wpsf",
    "text_domain":             "wp-simple-firewall",
    "base_permissions":        "manage_options",
    "wpms_network_admin_only": true,
    "logging_enabled":         true,
    "show_dashboard_widget":   true,
    "show_admin_bar_menu":     true,
    "autoupdate":              "confidence",
    "autoupdate_days":         2,
    "options_encoding":        "json",
    "enable_premium":          true
  },
  "requirements":     {
    "php":       "7.0",
    "wordpress": "3.7",
    "mysql":     "5.6"
  },
  "reqs_rest":        {
    "php": "7.0",
    "wp":  "5.7"
  },
  "upgrade_reqs":     {
    "10.0": {
      "php":   "7.0",
      "wp":    "3.5.2",
      "mysql": "5.5"
    },
    "12.0": {
      "php":   "7.0",
      "wp":    "3.7",
      "mysql": "5.6"
    },
    "16.2": {
      "php":   "7.0",
      "wp":    "4.7",
      "mysql": "5.6"
    }
  },
  "paths":            {
    "config":           "config",
    "source":           "src",
    "autoload":         "lib/vendor/autoload.php",
    "assets":           "resources",
    "languages":        "languages",
    "templates":        "templates",
    "custom_templates": "shield_templates",
    "flags":            "flags",
    "cache":            "shield"
  },
  "includes":         {
    "admin":        {
      "css": [
        "global-plugin"
      ],
      "js":  [
        "global-plugin",
        "shield/ip_detect"
      ]
    },
    "plugin_admin": {
      "css": [
        "select2",
        "plugin",
        "jquery/featherlight",
        "introjs",
        "shield/datatables"
      ],
      "js":  [
        "select2",
        "plugin",
        "jquery/featherlight",
        "jquery/fileDownload",
        "shield/ipanalyse",
        "shield/scanners",
        "shield/tours",
        "shield/datatables/audit_trail",
        "shield/datatables/ip_rules",
        "shield/datatables/scans",
        "shield/datatables/traffic",
        "tp/circular-progress"
      ]
    },
    "frontend":     {
      "css": [],
      "js":  []
    },
    "register":     {
      "css": {
        "bootstrap":                         {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.2.1/css/bootstrap.min.css"
        },
        "select2":                           {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css",
          "deps": [
            "plugin"
          ]
        },
        "datatables":              {
          "url":  "https://cdn.datatables.net/v/bs5/dt-1.12.1/b-2.2.3/sp-2.0.2/sl-1.4.0/datatables.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "jquery/smartwizard":                {
          "url": "https://cdn.jsdelivr.net/npm/smartwizard@6/dist/css/smart_wizard_all.min.css"
        },
        "global-plugin":                     {},
        "plugin":                            {
          "deps": [
            "bootstrap",
            "global-plugin"
          ]
        },
        "shield/merlin":                     {
          "deps": [
            "bootstrap",
            "global-plugin",
            "jquery/smartwizard"
          ]
        },
        "jquery/featherlight":               {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/featherlight/1.7.13/featherlight.min.css"
        },
        "chartist":                          {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.css"
        },
        "chartist-plugin-legend":            {
          "deps": [
            "chartist"
          ]
        },
        "introjs":                           {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/intro.js/3.3.1/introjs.min.css"
        },
        "shield/userprofile":                {
          "deps":   [],
          "footer": true
        },
        "shield/charts":                     {
          "deps": [
            "plugin"
          ]
        },
        "shield/dialog":                     {
          "deps":   [
            "wp-wp-jquery-ui-dialog"
          ],
          "footer": true
        },
        "shield/datatables":                 {
          "deps": [
            "datatables",
            "tp/highlightjs"
          ]
        },
        "shield/login2fa":                   {
        },
        "shield/integrations/mainwp-server": {},
        "tp/highlightjs":                    {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.1.0/styles/default.min.css"
        }
      },
      "js":  {
        "bootstrap":                         {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.2.1/js/bootstrap.bundle.min.js",
          "deps": [
            "wp-jquery"
          ]
        },
        "tp/circular-progress":              {
          "url":  "https://cdn.jsdelivr.net/gh/tomik23/circular-progress-bar@1.1.9/dist/circularProgressBar.min.js",
          "deps": [
          ]
        },
        "select2":                           {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js",
          "deps": [
            "plugin"
          ]
        },
        "datatables":                        {
          "url":  "https://cdn.datatables.net/v/bs5/dt-1.12.1/b-2.2.3/sp-2.0.2/sl-1.4.0/datatables.min.js",
          "deps": [
            "bootstrap",
            "wp-jquery"
          ]
        },
        "global-plugin":                     {
          "deps": [
            "wp-jquery"
          ]
        },
        "plugin":                            {
          "deps": [
            "bootstrap",
            "datatables",
            "global-plugin",
            "shield/navigation",
            "shield/ipanalyse",
            "shield/tables",
            "base64.min",
            "lz-string.min"
          ]
        },
        "base64.min":                        {
          "url": "https://cdn.jsdelivr.net/npm/js-base64@2.6.4/base64.min.js"
        },
        "lz-string.min":                     {},
        "jquery/fileDownload":               {},
        "jquery/smartwizard":                {
          "url":  "https://cdn.jsdelivr.net/npm/smartwizard@6/dist/js/jquery.smartWizard.min.js",
          "deps": [
            "wp-jquery"
          ]
        },
        "jquery/featherlight":               {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/featherlight/1.7.13/featherlight.min.js"
        },
        "chartist":                          {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
        },
        "chartist-plugin-legend":            {
          "deps": [
            "chartist"
          ]
        },
        "introjs":                           {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/intro.js/3.3.1/intro.min.js"
        },
        "shield/charts":                     {
          "deps": [
            "chartist",
            "chartist-plugin-legend",
            "plugin"
          ]
        },
        "shield/dialog":                     {
          "deps": [
            "wp-jquery-ui-dialog"
          ]
        },
        "shield/loginbot":                   {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/navigation":                 {},
        "shield/secadmin":                   {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/scanners":                   {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/tables":                     {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/ip_detect":                  {
          "deps": [
            "global-plugin",
            "wp-jquery"
          ]
        },
        "shield/datatables/audit_trail":     {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/ip_rules":        {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/scans":           {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/traffic":         {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/common":          {
          "deps": [
            "datatables",
            "tp/highlightjs"
          ]
        },
        "shield/tours":                      {
          "deps": [
            "plugin",
            "introjs"
          ]
        },
        "shield/notbot":                     {
        },
        "shield/scans":                      {
          "deps": [
            "shield/tables"
          ]
        },
        "shield/import":                     {
          "deps": [
            "plugin"
          ]
        },
        "shield/ipanalyse":                  {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/integrations/mainwp-server": {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/userprofile":                {
          "deps":   [
            "u2f-bundle",
            "shield/dialog"
          ],
          "footer": true
        },
        "shield/merlin":                     {
          "deps": [
            "bootstrap",
            "global-plugin",
            "jquery/smartwizard"
          ]
        },
        "shield/login2fa":                   {
          "deps": [
            "u2f-bundle",
            "wp-jquery"
          ]
        },
        "u2f-bundle":                        {},
        "tp/grecaptcha":                     {
          "url":        "https://www.google.com/recaptcha/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/hcaptcha":                       {
          "url":        "https://hcaptcha.com/1/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/highlightjs":                    {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.1.0/highlight.min.js"
        }
      }
    }
  },
  "menu":             {
    "show":           true,
    "top_level":      true,
    "do_submenu_fix": true,
    "has_submenu":    true
  },
  "labels":           {
    "Name":               "Shield Security",
    "MenuTitle":          "Shield Security",
    "Description":        "Ultimate WP Security Protection - Scans, 2FA, Firewall, SPAM, Activity Log, Security Admin, and so much more.",
    "Title":              "Shield Security",
    "Author":             "Shield Security",
    "AuthorName":         "Shield Security",
    "PluginURI":          "https://shsec.io/2f",
    "AuthorURI":          "https://shsec.io/bv",
    "url_img_pagebanner": "pluginlogo_banner-1544x500.png",
    "icon_url_16x16":     "pluginlogo_16x16.png",
    "icon_url_32x32":     "pluginlogo_32x32.png",
    "icon_url_128x128":   "pluginlogo_128x128.png"
  },
  "meta":             {
    "url_repo_home":       "https://shsec.io/eh",
    "privacy_policy_href": "https://shsec.io/shieldprivacypolicy"
  },
  "plugin_meta":      [
    {
      "name": "5&#10025; Rate This Plugin",
      "href": "https://shsec.io/wpsf29"
    }
  ],
  "version_upgrades": [
    "14.1.1",
    "14.1.4",
    "16.1.2"
  ],
  "action_links":     {
    "remove": null,
    "add":    [
      {
        "name":   "Security Dashboard",
        "title":  "Go To Security Dashboard",
        "href":   "getPluginUrl_DashboardHome",
        "target": "_top",
        "show":   "always"
      },
      {
        "name":      "&uarr; Go Pro &uarr;",
        "title":     "Get All PRO Security Features",
        "href":      "https://shsec.io/d8",
        "target":    "_blank",
        "highlight": true,
        "show":      "free"
      }
    ]
  },
  "modules":          [
    "plugin",
    "data",
    "admin_access_restriction",
    "audit_trail",
    "autoupdates",
    "comments_filter",
    "comms",
    "email",
    "events",
    "firewall",
    "hack_protect",
    "headers",
    "insights",
    "integrations",
    "ips",
    "license",
    "lockdown",
    "login_protect",
    "reporting",
    "sessions",
    "traffic",
    "user_management"
  ]
}