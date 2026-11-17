---
title: "Control Who Can Edit the Tag Groups"
order: 26
doc_category: checklists
---

# Control Who Can Edit the Tag Groups

Table of Contents

- [Who should be allowed to edit tag groups?](#0-toc-title)
- [Who should be allowed to change the groups on the tags page?](#1-toc-title)
- [Who should be allowed to override restrictions to add new tags and change tags in the meta box?](#2-toc-title)
- [What happens if users try to do something that is not allowed for their role?](#3-toc-title)

It is possible to control who can edit groups in the [Tag Groups](https://publishpress.com/links/tag-groups) plugin. You find the relevant settings in the “Back End” section under the “Permissions” tab. This menu appears only in the Pro version of [Tag Groups](https://publishpress.com/links/tag-groups).

### Who should be allowed to edit tag groups? [#](#0-toc-title)

This option lets you hide the page where you can create, rename, delete and sort tag groups for certain user roles. The administrator always has access. The minimum user role to access this page can be administrator, editor or author.

### Who should be allowed to change the groups on the tags page? [#](#1-toc-title)

Here you can set the minimum role of users who can assign tags to groups or change the groups of tags. It is effective only on the tag page (where you create and edit tags).

This option is usually only relevant to restrict the permissions of the user role “editor”.((By default authors cannot edit tags. For some custom taxonomies, however, it might be different.)) It complements the following one.

### Who should be allowed to override restrictions to add new tags and change tags in the meta box? [#](#2-toc-title)

This option makes only sense if you use the settings in the Meta Box tab to restrict the creation of tags or shifting tags to another group. Here you can set a user role that is able to override this restriction. Available is administrator, editor or author.

This permission applies only to the meta box. Editors will still see the tags page and can change the groups there, unless you use the option mentioned above.

### What happens if users try to do something that is not allowed for their role? [#](#3-toc-title)

- If a user tries to add a new tag in the Meta Box, it will be refused with a message.
- If a user tries to move a tag in the Meta Box to a different group, it will be refused with a message.
- If a user adds a new tag in the default tag input box or through Quick Edit on the post listing, the tag will be added but remains unassigned to any group. A user who has the required permissions will then be able to assign it to a group.
- If users are not allowed to edit tag groups, they won’t even see the corresponding page and any attempt would be refused.
- If users are not allowed to edit the groups of tags, they won’t even see the corresponding menus and any attempt would be refused.
