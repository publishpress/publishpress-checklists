---
title: "Programmatically Configured Checklists"
order: 41
doc_category: checklists
---

# Programmatically Configured Checklists

Table of Contents

- [Available Requirements](#0-toc-title)
- [Available Rules](#1-toc-title)
- [Example Code](#2-toc-title)

In the [PublishPress Checklists](https://publishpress.com/links/checklists) plugin, it is possible to configure checklists requirement using code for your WordPress site.

This example code show how to do that:

```
ppch_set_requirement(
    string $requirement_name,           // Name of the requirement to configure
    array $config,                      // Configuration array with rule, params, etc.
    string|array $post_types = 'all'    // Post type(s) to apply the requirement to
);
```

The `$config` array accepts the following structure:

```
 'rule' => string,          // 'required', 'recommended', or 'disabled'
 'params' => array,         // Requirement-specific parameters
 'ignored_by' => array,     // Array of role slugs that can bypass this requirement
 'terms' => array          // For taxonomy requirements: array of term IDs
```

This unified approach replaces multiple separate methods with one comprehensive function that can handle all aspects of a requirement:

- Setting the rule (required, recommended, or disabled)
- Configuring parameters (like minimum word count)
- Specifying which user roles can ignore the requirement

## Available Requirements [#](#0-toc-title)

We provides a comprehensive set of requirements that you can configure:

**Core Requirements**

- `words_count`: Check minimum/maximum word count in content
- `featured_image`: Ensure a featured image is set
- `categories_count`: Enforce minimum/maximum number of categories
- `tags_count`: Control minimum/maximum number of tags
- `filled_excerpt`: Validate excerpt length with min/max characters
- `title_count`: Set minimum/maximum words in title
- `external_links`: Track number of external links
- `internal_links`: Monitor number of internal links
- `image_alt_count`: Count images with alt text
- `image_alt`: Ensure all images have alt text
- `validate_links`: Check if all links work
- `featured_image_alt`: Require alt text for featured images
- `permalink_valid_chars`: Validate permalink characters

**Taxonomy Requirements**

- `required_categories`: Ensure specific categories are selected
- `prohibited_categories`: Prevent specific categories from being selected
- `required_tags`: Ensure specific tags are selected
- `prohibited_tags`: Prevent specific tags from being selected
- `taxonomies_count`: Control term count for custom taxonomies

**Editorial Requirements**

- `approved_by`: Require approval from specific user roles

## Available Rules [#](#1-toc-title)

For each requirement, you can set one of three rules:

- `required`: The requirement must be met before publishing (blocks publishing)
- `recommended`: The requirement is recommended but not mandatory (shows a warning)
- `disabled`: The requirement is disabled

## Example Code [#](#2-toc-title)

Let’s look at some practical examples of how you can use this:

**Example 1: Set Minimum Word Count**

```
if (function_exists('ppch_set_requirement')) {
    add_action('init', function() {
        ppch_set_requirement('words_count', [
            'rule' => 'required',
            'params' => [
                'min' => 500
            ],
            'ignored_by' => ['administrator']
        ]);
    });
}
```

This code sets the word count requirement to:

– Be required (will block publishing if not met)

– Have a minimum of 500 words

– Allow administrators to bypass this requirement

**Example 2: Require Featured Images for Posts**

```
if (function_exists('ppch_set_requirement')) {
    add_action('init', function() {
        ppch_set_requirement('featured_image', [
            'rule' => 'required'
        ], 'post');
    });
}
```

This simple code makes featured images required for all posts.

**Example 3: Set Required Categories by ID**

```
if (function_exists('ppch_set_requirement')) {
    add_action('init', function() {
        ppch_set_requirement('required_categories', [
            'rule' => 'required',
            'terms' => [5, 10], // Specify categories by their IDs
            'ignored_by' => ['administrator']
        ], ['post', 'page']); // Apply to both posts and pages
    });
}
```

This requires at least 2 categories for both posts and pages.
