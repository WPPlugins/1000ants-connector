=== 1000ants Connector ===
Contributors: 1000ants
Tags: seo, redirects, robots.txt, site audit, automation
Requires at least: 6.2
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets 1000ants apply prepared fixes directly on your site — only after your approval, recorded and reversible.

== Description ==

1000ants finds what holds your website back and prepares the fixes. Without this plugin, some of those fixes can only be handed to you as a to-do, because WordPress does not let an outside application write them. With it, the ants apply them on your site themselves.

**You stay in control:**

* **Your approval first.** Nothing is written until you approve the change in 1000ants.
* **Every change is recorded.** What changed, when and by whom stays in your 1000ants change log.
* **Every change can be undone** from 1000ants, one by one — and a change somebody made by hand in the meantime is never overwritten.
* **The plugin only opens what is needed.** Writing needs the same WordPress right as editing the page itself; site-wide settings need an administrator.

**What the ants can apply today:**

* SEO titles and meta descriptions in Rank Math or Yoast SEO (the values stay in your SEO plugin).
* Redirects for addresses that are gone — they only fire where WordPress would show a 404, and only to pages on your own site.
* robots.txt rules, appended to the robots.txt WordPress serves.
* The schema type of a page in Rank Math: Article markup for an article, none for a page that is no article — or, where the cause is Rank Math's default for all pages of a type, that one setting.

More fixes will follow in updates; each one keeps the same three promises.

== External services ==

This plugin does not contact any service and sends no data anywhere. It adds a few routes to your site's own REST API, which the 1000ants service (https://1000ants.ai) calls with the application password you create when you connect WordPress in 1000ants. You can revoke that password in WordPress at any time.

* Terms of service: https://1000ants.ai/terms-of-service
* Privacy policy: https://1000ants.ai/privacy-policy

== Installation ==

1. Upload the plugin in WordPress (Plugins → Add New → Upload Plugin) and activate it.
2. In 1000ants, connect WordPress with an application password (Users → Profile → Application Passwords).
3. That's it: 1000ants detects the plugin, and fixes that were to-dos arrive as changes you can approve.

== Frequently Asked Questions ==

= Do I need an SEO plugin? =

For SEO titles and descriptions, Rank Math or Yoast SEO. Redirects and robots.txt rules work without one.

= What happens when I remove the plugin? =

Its redirects and robots.txt rules are deleted. Titles, descriptions and schema settings it helped write stay in your SEO plugin, where they belong.

= Does the plugin add anything to my pages? =

No scripts, no styles, no tracking. It only answers the requests 1000ants makes with your permission.

== Changelog ==

= 1.2.2 =
* Undoing a schema change takes back exactly that change, never another one made on the same page.
* Removing markup from SEO titles keeps text such as "<50%" intact.

= 1.2.1 =
* The undo of a schema change restores what the plugin kept itself, never data sent with the request.
* SEO titles and descriptions keep no markup from users who may not post HTML.

= 1.2.0 =
* The default schema type of a post type in Rank Math, as one undoable change.

= 1.1.0 =
* The schema type of a page in Rank Math (Article, BlogPosting, NewsArticle or none), undoable.

= 1.0.0 =
* First release: SEO titles and descriptions for Rank Math and Yoast SEO, redirects for gone addresses, robots.txt rules.
