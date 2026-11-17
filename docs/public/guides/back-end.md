---
title: "Back End Settings in Tag Groups"
order: 8
doc_category: checklists
---

# Back End Settings in Tag Groups

Table of Contents

- [Filters](#0-toc-title)
- [Gutenberg](#1-toc-title)
- [Multilingual](#2-toc-title)
- [Tag Meta Box](#3-toc-title)
- [Permissions](#4-toc-title)
- [Default Groups](#5-toc-title)

This guide explained how to use the settings in the “Back End” area of the [Tag Groups](https://publishpress.com/links/tag-groups) plugin.

## Filters [#](#0-toc-title)

Two filters are available for the back end:

- A post filter can filter the list of posts by the [tag groups](https://publishpress.com/links/tag-groups) that their tags belong to.
- A tag filter can filter tags by tag groups.

The post filter is not available for categories.

In rare cases the filters conflict with filters of other plugins. In that case you can disable them here.

## Gutenberg [#](#1-toc-title)

This menu appears only if the Gutenberg editor is enabled.

At this place you can disable Gutenberg block previews.

## Multilingual [#](#2-toc-title)

This menu appears only if the WPML plugin is enabled. Some options may also work with the Polylang plugin.

Here you can decide if you want to sync all groups of a tag across its translations when you save this tag. In most cases all language variations will use the same groups but you also may want to assign them independently.

## Tag Meta Box [#](#3-toc-title)

This menu appears only in the Pro version of Tag Groups. Here you can choose for which of your active Taxonomies the Tag Meta Box will appear. The Tag Meta Box works only for one taxonomy per post type and only for non-hierarchical taxonomies, such as Tags (post_tag) or Product Tags (product_tag).

You also have four options regarding the way how tags can be entered:

- You can automatically open all groups that contain tags of the post that you start editing.
- You can hide the default tag box so that you will work only with tags that are assigned to groups.
- You can restrict the tags entered in the Tag Meta Box to existing tags. That option might be useful if you want to set up a pool of allowed tags and your post authors should not be able to add new tags.
- You can prevent tags from being moved to a new group. Otherwise authors would be able to enter a tag that already exists in another group, that way moving it to a new group.

Optionally select the groups that should be available and remove the menu. That makes it possible to present your authors only a certain selection of groups.

**Note 1:**Since tags are separated in the input field by comma (“,”) or semicolon (“;”), **tag names cannot contain these characters** in order to work with the Tag Meta Box.

**Note 2:**The Tag Meta Box works only correctly if tag names are **unique**. It is not sufficient if tags with identical names are differentiated by their “slugs” (piece of the link).

## Permissions [#](#4-toc-title)

This menu appears only in the Pro version of Tag Groups. On the Permissions screen, you can determine who is allowed to edit tag groups. That way you can prevent that authors make changes to the groups, while they are still allowed to use them.

Here you can also allow exceptions to the rule that prevents the creation of new tags, or moving tags to another group in the Tag Meta Box.

[Read more about the available permissions and their effects](https://taxopress.com/docs/how-can-i-use-permissions-to-restrict-editing-of-tag-groups/).

## Default Groups [#](#5-toc-title)

This menu appears only in the Pro version of Tag Groups. By default, all new tags are unassigned. In the Default Groups settings, however, you can define groups that will be automatically assigned to all new tags that were created without assigning any groups. On the post edit screen you enter them in the default tag field or in the “not assigned” tag field.

Default groups will also be effective for users who actually don’t have the permission to assign tags to groups.

Naturally, when you now want to create a tag that is *not* assigned to any group, you need to go one more step and remove the groups manually.

Default groups cannot be applied retroactively. For that purpose you can use bulk assignment of groups on the Tags page.
