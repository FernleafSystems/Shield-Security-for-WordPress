{
  "slug":       "headers",
  "properties": {
    "slug":                  "headers",
    "name":                  "HTTP Headers",
    "sidebar_name":          "HTTP Headers",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "headers",
    "tagline":               "Control HTTP Security Headers",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          false,
    "order":                 80
  },
  "sections":   [
    {
      "slug":        "section_security_headers",
      "primary":     true,
      "title":       "Advanced Security Headers",
      "title_short": "Security Headers",
      "summary":     [
        "Purpose - Protect visitors to your site by implementing increased security response headers.",
        "Recommendation - Enabling these features are advised, but you must test them on your site thoroughly."
      ]
    },
    {
      "slug":        "section_content_security_policy",
      "title":       "Content Security Policy",
      "title_short": "Content Security Policy",
      "summary":     [
        "Purpose - Restrict the sources and types of content that may be loaded and processed by visitor browsers.",
        "Recommendation - Enabling these features are advised, but you must test them on your site thoroughly."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_headers",
      "title":       "Enable Module: HTTP Headers",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - Protect visitors to your site by implementing increased security response headers.",
        "Recommendation - Enabling these features are advised, but you must test them on your site thoroughly."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":    [
    {
      "key":         "enable_headers",
      "section":     "section_enable_plugin_feature_headers",
      "advanced":    true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/aj",
      "link_blog":   "https://shsec.io/7c",
      "name":        "Enable HTTP Headers",
      "summary":     "Enable (or Disable) The HTTP Headers module",
      "description": "Un-Checking this option will completely disable the HTTP Headers module"
    },
    {
      "key":           "x_frame",
      "section":       "section_security_headers",
      "default":       "on_sameorigin",
      "type":          "select",
      "value_options": [
        {
          "value_key": "off",
          "text":      "Off: iFrames Not Blocked"
        },
        {
          "value_key": "on_sameorigin",
          "text":      "On: Allow iFrames On The Same Domain"
        },
        {
          "value_key": "on_deny",
          "text":      "On: Block All iFrames"
        }
      ],
      "link_info":     "https://shsec.io/78",
      "link_blog":     "https://shsec.io/7c",
      "name":          "Block iFrames",
      "summary":       "Block Remote iFrames Of This Site",
      "description":   "The setting prevents any external website from embedding your site in an iFrame. This is useful for preventing so-called ClickJack attacks."
    },
    {
      "key":         "x_xss_protect",
      "section":     "section_security_headers",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/79",
      "link_blog":   "https://shsec.io/7c",
      "name":        "XSS Protection",
      "summary":     "Employ Built-In Browser XSS Protection",
      "description": "Directs compatible browsers to block what they detect as Reflective XSS attacks."
    },
    {
      "key":         "x_content_type",
      "section":     "section_security_headers",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/7a",
      "link_blog":   "https://shsec.io/7c",
      "name":        "Prevent Mime-Sniff",
      "summary":     "Turn-Off Browser Mime-Sniff",
      "description": "Reduces visitor exposure to malicious user-uploaded content."
    },
    {
      "key":           "x_referrer_policy",
      "section":       "section_security_headers",
      "sensitive":     false,
      "type":          "select",
      "default":       "unsafe-url",
      "value_options": [
        {
          "value_key": "unsafe-url",
          "text":      "Default: Full Referrer URL (aka 'Unsafe URL')"
        },
        {
          "value_key": "no-referrer",
          "text":      "No Referrer"
        },
        {
          "value_key": "no-referrer-when-downgrade",
          "text":      "No Referrer When Downgrade"
        },
        {
          "value_key": "same-origin",
          "text":      "Same Origin"
        },
        {
          "value_key": "origin",
          "text":      "Origin"
        },
        {
          "value_key": "strict-origin",
          "text":      "Strict Origin"
        },
        {
          "value_key": "origin-when-cross-origin",
          "text":      "Origin When Cross-Origin"
        },
        {
          "value_key": "strict-origin-when-cross-origin",
          "text":      "Strict Origin When Cross-Origin"
        },
        {
          "value_key": "empty",
          "text":      "Empty Header"
        },
        {
          "value_key": "disabled",
          "text":      "Disabled - Don't Send This Header"
        }
      ],
      "link_info":     "https://shsec.io/a5",
      "link_blog":     "",
      "name":          "Referrer Policy",
      "summary":       "Referrer Policy Header",
      "description":   "The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site."
    },
    {
      "key":         "enable_x_content_security_policy",
      "section":     "section_content_security_policy",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/7d",
      "link_blog":   "https://shsec.io/7c",
      "name":        "Enable Content Security Policy",
      "summary":     "Enable (or Disable) The Content Security Policy module",
      "description": "Allows for permission and restriction of all resources loaded on your site."
    },
    {
      "key":         "xcsp_custom",
      "section":     "section_content_security_policy",
      "premium":     true,
      "default":     [],
      "type":        "array",
      "link_info":   "https://shsec.io/g9",
      "link_blog":   "",
      "name":        "Manual Rules",
      "summary":     "Manual CSP Rules",
      "description": "Manual CSP rules."
    }
  ]
}