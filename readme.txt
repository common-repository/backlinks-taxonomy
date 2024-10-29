=== Backlinks Taxonomy ===
Contributors: seindal
Tags: backlinks, link building, taxonomy
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The purpose of this plugin is to help internal link building, for SEO and for a better user experience.

The internal link structure of a site is important for site usability, and for search engine optimisation.

== Description ==

This plugin is a minimalist backlink tracker. It keeps track of all internal links between posts using taxonomies.

The plugin maintains a taxonomy of which posts links to which. The taxonomy is mostly invisible, but available through REST.

The backlink taxonomy is updated automatically whenever a post is published, scheduled or later updated. By registering the outgoing links of all posts it is easy to find the incoming links for any one post.

== User Interface ==

The plugin most works behind the scenes, scanning posts for internal links whenever the posts are updated.

The backlink taxonomy is available for the Query Loop block, which can be used to create a dynamic "What links here" block.

The backlink_count taxonomy can be used to filter the displayed posts on the posts lists of the admin interface. This is a handy way of finding posts with no or few incoming links.

The admin posts list has a "Suggest Backlinks" added for each row, which will find posts with shared tags and categories, that are not already linked from the post. If there are shared public custom taxonomies, they will be used too.

The admin posts list also has a 'Backlinks' column available, showing the number of backlinks for the post. Clicking on the column will show a list of the backlinks. The column is sortable. The column also shows the number of outgoing links, also clickable.

A management page under the Tools menu can show the backlinks status of all posts, with columns for incoming and outgoing links, and commands for rescanning or 'un-scanning' posts.

The settings pages is rather basic, but allows the setting of post types to operate on (default posts and pages), and post statuses to select on (default published and scheduled).

Un-scanned posts, for example just after activation of the plugin or after a change of settings, are scanned in the background at bit at a time. During normal usage there should never be a backlog of un-scanned posts.

The Backlinks Taxonomy plugin works with custom post types, and will use available custom taxonomies for backlink suggestions.

== WP-CLI interface ==

The plugin adds a WP-CLI command 'backlinks' with several sub-commands.

There are commands to:

* Show all outgoing and incoming links for a single post;
* Show backlink suggestions for a single post;
* Show the overall status of the backlink counters;
* Rescan individual posts for links;
* List all un-scanned posts.

== Installation ==

The plugin can be installed as any other plugin. There are no particular requirements.

== Frequently Asked Questions ==

Nothing yet.

== Changelog ==

= 2.2 =

* Some Improvements to backlink suggestions.
* Un-scanned posts are now scanned in a background job.
* Updates to the internal plugin development framework.

= 2.1 =

* Tested with WP 6.5
* Fixed a bug where the "Suggest backlinks" link was shown for non-eligible post types.

= 2.0 =

* Can now handle links between many different post types;
* Back- and out-links are now displayed on a separate management page, under the Tools menu, which can list posts of several post types together, with additional commands and back- and out-links for each post;
* Link suggestions now include all configured post types and shared taxonomies.

= 1.0 =

* First version in the WordPress plugin registry;
* Tested with WP 6.4.

== Upgrade Notice ==

Nothing yet.
