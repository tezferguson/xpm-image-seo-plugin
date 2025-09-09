=== XPM Image SEO ===
Contributors: xploitedmedia
Donate link: https://xploited.media/donate
Tags: seo, accessibility, alt text, openai, ai, images, screen readers, bulk update
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Boost your website's SEO and accessibility with AI-powered alt text generation. Automatically create descriptive alt text for images using OpenAI's Vision API.

== Description ==

**XPM Image SEO** is a powerful WordPress plugin developed by Xploited Media that leverages OpenAI's advanced Vision API to automatically generate SEO-optimized alt text for your images. Improve your website's accessibility, search engine rankings, and user experience with intelligent image descriptions.

🚀 **Key Features:**

* **AI-Powered Alt Text Generation** - Uses OpenAI's GPT-4o-mini Vision API for accurate, contextual descriptions
* **Bulk Update Tool** - Process hundreds of existing images without alt text in one go
* **SEO Optimization** - Generates alt text specifically designed for search engine visibility
* **Accessibility Compliance** - Helps meet WCAG guidelines for screen reader users
* **Cost-Effective** - Optimized for minimal API usage while maintaining quality
* **Customizable Prompts** - Tailor the AI's output to match your brand voice
* **Real-Time Progress Tracking** - Monitor bulk updates with detailed logging
* **Automatic Processing** - Optionally generate alt text for new uploads automatically

🎯 **Benefits:**

* **Improve SEO Rankings** - Search engines better understand your images
* **Enhance Accessibility** - Make your site usable for visually impaired visitors  
* **Save Time** - Automate the tedious task of writing alt text
* **Ensure Consistency** - AI-generated descriptions maintain quality standards
* **Boost User Experience** - Better image context for all users

🔧 **Perfect For:**

* E-commerce websites with large product catalogs
* Photography and portfolio sites
* News and blog websites
* Corporate websites requiring accessibility compliance
* Any WordPress site wanting to improve SEO and accessibility

📋 **Requirements:**

* WordPress 5.0 or higher
* PHP 7.4 or higher  
* OpenAI API key (get yours at https://platform.openai.com/api-keys)
* cURL support (standard on most hosting providers)

== Installation ==

1. **Upload and Activate:**
   - Download the plugin zip file
   - Go to WordPress Admin > Plugins > Add New > Upload Plugin
   - Choose the zip file and click "Install Now"
   - Activate the plugin

2. **Configure API Key:**
   - Go to Settings > XPM Image SEO
   - Enter your OpenAI API key
   - Save settings

3. **Start Using:**
   - For existing images: Go to Media > Bulk Alt Text Update
   - For new uploads: Enable auto-generation in settings

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

Yes, this plugin requires an OpenAI API key to function. You can get one from https://platform.openai.com/api-keys. Make sure you have sufficient credits in your OpenAI account.

= How much does it cost to use? =

The plugin uses OpenAI's GPT-4o-mini model, which is very cost-effective. Typical costs are around $0.001-0.003 per image, depending on image complexity. For example, processing 1000 images might cost $1-3 USD.

= Can I customize how the alt text is generated? =

Absolutely! You can provide a custom prompt in the plugin settings to tailor the AI's output to your specific needs, brand voice, or industry requirements.

= Does it work with existing images? =

Yes! The bulk update tool scans your entire media library for images without alt text and allows you to process them in batches with real-time progress tracking.

= Is it safe for my website? =

Yes, the plugin includes comprehensive security measures, rate limiting, and error handling. All API calls are made securely, and your images are processed through OpenAI's secure API.

= Will it slow down my website? =

No, the plugin processes images in the background and includes rate limiting to prevent server overload. The bulk update tool processes images one at a time to maintain site performance.

= What image formats are supported? =

The plugin works with all standard WordPress image formats (JPEG, PNG, GIF, WebP) that are supported by OpenAI's Vision API.

= Can I review alt text before it's applied? =

While the plugin applies alt text automatically, all changes are logged in the bulk update interface. You can always manually edit alt text in your WordPress media library after generation.

= Does it work with multilingual sites? =

The plugin interface supports translation, and you can customize prompts to generate alt text in different languages by specifying the language in your custom prompt.

== Screenshots ==

1. **Settings Page** - Configure your OpenAI API key and customize generation behavior
2. **Bulk Update Interface** - Scan and process multiple images with progress tracking  
3. **Real-time Progress** - Monitor the bulk update process with detailed logging
4. **Image Preview Grid** - See which images will be processed before starting
5. **Successful Results** - View generated alt text and processing statistics

== Changelog ==

= 1.0.0 =
* Initial release by Xploited Media
* AI-powered alt text generation using OpenAI GPT-4o-mini
* Bulk update tool with progress tracking
* Customizable prompts for tailored output
* Automatic generation for new uploads
* Comprehensive error handling and logging
* SEO and accessibility optimized descriptions
* Rate limiting to prevent API overuse
* Responsive admin interface
* Security measures and input validation

== Upgrade Notice ==

= 1.0.0 =
Welcome to XPM Image SEO! Configure your OpenAI API key in Settings > XPM Image SEO to start generating SEO-optimized alt text for your images.

== Developer Information ==

**Plugin Author:** Xploited Media  
**Website:** https://xploited.media  
**Support:** For technical support and feature requests, please visit our website.

This plugin is developed and maintained by Xploited Media, a digital agency specializing in WordPress development, SEO optimization, and accessibility solutions.

== Privacy & Data Usage ==

This plugin sends image data to OpenAI's API for processing. Please review OpenAI's privacy policy at https://openai.com/privacy/. The plugin:

* Sends images to OpenAI only when generating alt text
* Does not store images on external servers
* Logs alt text generation locally in your WordPress database
* Does not collect personal user information
* Allows you to control when and how images are processed

== Technical Notes ==

**API Usage Optimization:**
* Uses GPT-4o-mini model for cost efficiency
* Implements low-detail image processing to reduce token usage
* Includes intelligent rate limiting (2-second delays between requests)
* Processes images asynchronously to prevent timeouts

**Performance:**  
* Minimal impact on site performance
* Background processing for new uploads
* Efficient database queries for bulk operations
* Responsive admin interface with modern JavaScript

**Compatibility:**
* WordPress 5.0+ 
* PHP 7.4+
* Works with popular page builders
* Compatible with media management plugins
* Supports WordPress multisite networks

**Security:**
* Nonce verification for all AJAX requests
* Capability checks for user permissions
* Input sanitization and output escaping
* Secure API communication with OpenAI

== Support ==

For support, feature requests, or custom development needs, visit [Xploited Media](https://xploited.media).

**Quick Links:**
* [Get OpenAI API Key](https://platform.openai.com/api-keys)
* [WordPress Accessibility Guidelines](https://make.wordpress.org/accessibility/)
* [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)