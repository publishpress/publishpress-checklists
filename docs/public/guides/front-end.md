---
title: "Front End Settings"
order: 23
doc_category: checklists
---

# Front End Settings

Table of Contents

- [Shortcodes](#0-toc-title)[Enable shortcode in sidebar widgets](#1-toc-title)[Always load shortcode scripts](#2-toc-title)
- [Themes and Appearance](#3-toc-title)[Further Options](#4-toc-title)
- [Tag Colors](#5-toc-title)
- [Post Template](#6-toc-title)
- [Cache](#7-toc-title)[Automatically clear cache when needed. Show a notification if turned off.](#8-toc-title)
- [Post Tags](#post-tags)
- [WooCommerce](#10-toc-title)

This guide explains the settings in the “Front End” area of the [Tag Groups](https://publishpress.com/links/tag-groups) Pro plugin. You can see the “Front End” tabs in this screenshot below.

![](https://docs-media.publishpress.com/publishpress-checklists/front-end-settings-8822d660a6.webp)

## Shortcodes [#](#0-toc-title)

At this place you find a list of shortcodes that display tag clouds, menus or information about [tag groups](https://publishpress.com/links/tag-groups).

### Enable shortcode in sidebar widgets [#](#1-toc-title)

Depending on your theme and other plugins, shortcodes may already work in text widgets. If they still display on the front end in square brackets, you can enable them here for widgets.

### Always load shortcode scripts [#](#2-toc-title)

If you uncheck this option, then all scripts that are required for the particular feature will only be loaded if a block or shortcode can be found in the post or page content. This can help reduce the loading time of your pages where you don’t use shortcodes.

Since we cannot parse widgets and other places in your theme, you need to check this option if you use shortcodes in widgets (or shortcodes that were hard-coded into your theme).

## Themes and Appearance [#](#3-toc-title)

Here you can choose between three standard themes or set your own theme. This theme will be applied only to the tabs of the Tabbed Tag Cloud and the header elements of the Accordion Tag Cloud (i.e. the labels and the frames).

### Further Options [#](#4-toc-title)

Most of the options can also be set via shortcode parameters or in Gutenberg blocks. “Allow HTML in tag description” may be interesting if you use formatted tag descriptions, which is rarely the case.

## Tag Colors [#](#5-toc-title)

Starting from version 1.29.0, you find here a tool to design your tags. It creats the CSS that you can use in your theme or the WordPress Customizer (menu Appearance -> Customize). Just follow the steps on the screen from top to bottom.

![](https://docs-media.publishpress.com/publishpress-checklists/screenshot-tag-colors-back-end-wordpress-1024x529-1-93447382e4.webp)

## Post Template [#](#6-toc-title)

The Toggle Post Filter and the Dynamic Post Filter are mainly configured through shortcode parameters or Gutenberg block options. Since entering the template for post output makes this a bit troublesome, you have the option to compose and test it on this page.

In order to apply later on a page what you saved here in the settings, you need to keep the “template” parameter in the shortcode or Gutenberg block empty.

Please note that the preview is only approximate, as it is not aware of the frontend styling.

## Cache [#](#7-toc-title)

Caching helps your server speed up the generation of tag clouds and other front-end objects by saving the finished output and reusing it for some time, instead of recalculating everything again. In the settings you can turn it off or choose the location where the output will be saved (database or file system).

The options are:

- off: Tag clouds will be recreated every time the page is generated. If you use a third-party plugin for caching pages, the page won’t be generated but used from that plugin’s cache. This option is useful while you develop your site and don’t have visitors.
- database: The objects will be saved in the WordPress database. This method is usually very fast but on some systems you may need to avoid the additional burden on your database server. In that case it is recommended to use the WordPress object cache (see below) with an additional 3rd party component, such as [Redis](https://redis.io/).
- other: The default WordPress object cache will be used. This kind of cache is not persistent, unless you install a third-party plugin for object caching. Read more about it [here](https://codex.wordpress.org/Class_Reference/WP_Object_Cache#Persistent_Caching).

If you are unsure what to use, is recommended to try “off” while fine-tuning your website and then switch to “database”.

You can compare on the page Troubleshooting -> System Information -> Benchmarks the speed of the various cache options. The speed test will also reveal if a (session-persistent) WP object cache is working.

### Automatically clear cache when needed. Show a notification if turned off. [#](#8-toc-title)

Normally you want to keep this option on. Only on busy websites where you often modify posts you may switch to manual purging.

## Post Tags [#](#post-tags)

Once your posts use tags that are organized in groups, you may want to display them together with these groups under your posts. While displaying tags in posts and pages requires the modification of theme templates, the Tag Groups Pro plugin can insert the post tags at the bottom of the post content.

![](https://docs-media.publishpress.com/publishpress-checklists/screenshot-post-tags-under-content-e1532039374130-2bd9021b6d.webp)

*Post tags and their groups appear under the post in default styling, together with content added by other plugins, such as sharing buttons and related posts.*

In the settings you can specify:

- A title. The default is “Tags”. It is possible to leave it empty.
- The groups that should be included under posts.
- The page types where you want the tags to appear, if your theme allows.((Please also note that some – like feeds – might not render the table properly.))
- A number for the “priority” that determines the position among other blocks of content that is inserted by plugins. The lower the number, the higher the position. If another plugin already uses the smallest priority 1 then it might be impossible to place the tags above it.
- Try to remove the default (ungrouped) tags. Depending on your theme, there might still remain a title or empty box.
- Show also parent groups if you use them.

The appearance of the title and table can be adjusted with CSS (for advanced users). Developers can also use the filter “tag_groups_post_terms” to modify the HTML output before rendering.

## WooCommerce [#](#10-toc-title)

If you have WooCommerce installed and activated, you find a new tab “WooCommerce” with the following options:

- One lets you add tag groups and their tags to the attributes section (“Additional Information”) of the product. Of course, we display only groups and tags that you use with that particular product.
- The second one removes the “Tags: …” part from the single product view so that the tags don’t appear twice. Your product categories will still be in place, but tags are used only as additional attribute values.
- Lastly, you can set a separator that appears between the tags and attribute values.

![](https://docs-media.publishpress.com/publishpress-checklists/settings-front-end-woocommerce-wordpress-5d9b7123ce.webp)
