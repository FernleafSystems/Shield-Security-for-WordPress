{
  "slug":       "headers",
  "properties": {
    "slug":                  "headers",
    "name":                  "HTTP Headers",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "headers",
    "tagline":               "Control HTTP Security Headers",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   true,
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
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/aj",
      "link_blog":   "https://icwp.io/7c",
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
      "link_info":     "https://icwp.io/78",
      "link_blog":     "https://icwp.io/7c",
      "name":          "Block iFrames",
      "summary":       "Block Remote iFrames Of This Site",
      "description":   "The setting prevents any external website from embedding your site in an iFrame. This is useful for preventing so-called ClickJack attacks."
    },
    {
      "key":         "x_xss_protect",
      "section":     "section_security_headers",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/79",
      "link_blog":   "https://icwp.io/7c",
      "name":        "XSS Protection",
      "summary":     "Employ Built-In Browser XSS Protection",
      "description": "Directs compatible browsers to block what they detect as Reflective XSS attacks."
    },
    {
      "key":         "x_content_type",
      "section":     "section_security_headers",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/7a",
      "link_blog":   "https://icwp.io/7c",
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
          "text":      "Default: Full Referrer URL (AKA 'Unsafe URL')"
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
          "text":      "Don't Send This Header"
        }
      ],
      "link_info":     "https://icwp.io/a5",
      "link_blog":     "",
      "name":          "Referrer Policy",
      "summary":       "Referrer Policy Header",
      "description":   "The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site."
    },
    {
      "key":         "enable_x_content_security_policy",
      "section":     "section_content_security_policy",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/7d",
      "link_blog":   "https://icwp.io/7c",
      "name":        "Enable Content Security Policy",
      "summary":     "Enable (or Disable) The Content Security Policy module",
      "description": "Allows for permission and restriction of all resources loaded on your site."
    },
    {
      "key":         "xcsp_self",
      "section":     "section_content_security_policy",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Self",
      "summary":     "Allow 'self' Directive",
      "description": "Using 'self' is generally recommended. It essentially means that resources from your own host:protocol are permitted."
    },
    {
      "key":         "xcsp_inline",
      "section":     "section_content_security_policy",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Inline Entities",
      "summary":     "Allow Inline Scripts and CSS",
      "description": "Allows parsing of Javascript and CSS declared in-line in your html document."
    },
    {
      "key":         "xcsp_data",
      "section":     "section_content_security_policy",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Embedded Data",
      "summary":     "Allow 'data:' Directives",
      "description": "Allows use of embedded data directives, most commonly used for images and fonts."
    },
    {
      "key":         "xcsp_eval",
      "section":     "section_content_security_policy",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Allow eval()",
      "summary":     "Allow Javascript eval()",
      "description": "Permits the use of Javascript the eval() function."
    },
    {
      "key":         "xcsp_https",
      "section":     "section_content_security_policy",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "HTTPS",
      "summary":     "HTTPS Resource Loading",
      "description": "Allows loading of any content provided over HTTPS."
    },
    {
      "key":         "xcsp_hosts",
      "section":     "section_content_security_policy",
      "sensitive":   true,
      "default":     [
        "*"
      ],
      "type":        "array",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Permitted Hosts",
      "summary":     "Permitted Hosts and Domains",
      "description": "You can explicitly state which hosts/domain from which content may be loaded. Take great care and test your site as you may block legitimate resources. If in-doubt, leave blank or use '*' only. Note: You can force only HTTPS for a given domain by prefixing it with 'https://'."
    }
  ]
}