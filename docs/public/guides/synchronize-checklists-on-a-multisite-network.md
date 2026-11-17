---
title: "Synchronize Checklists on a Multisite Network"
order: 51
doc_category: checklists
---

# Synchronize Checklists on a Multisite Network

Table of Contents

- [#1. Detect the site ids of all the subsites you want to sync](#h-1-detect-the-site-ids-of-all-the-subsites-you-want-to-sync)
- [#2. Install the code snippet code into your functions.php file](#h-2-install-the-code-snippet-code-into-your-functions-php-file)
- [#3. Customize the variable "$siteIdsToSync" in the code](#h-3-customize-the-variable-siteidstosync-in-the-code)
- [#4. Configure the checklists](#h-4-configure-the-checklists)

[PublishPress Checklists](https://publishpress.com/links/checklists) supports multisite installations, but by default, all the options are saved individually for each site.

There is a workaround, if you want to share the Checklists options among multiple sites on a multisite networks.

The code below will make sure that all the selected subsites will receive the same Checklists options every time you save it.

### #1. Detect the site ids of all the subsites you want to sync [#](#h-1-detect-the-site-ids-of-all-the-subsites-you-want-to-sync)

You can find all the site IDs by loading the Sites list in your network settings and editing each site. The site ID will be the number you will find in the URL parameter “id”. In the image below, I hover over the “Edit” link and see that the site ID is 2.

![Multisite Id](https://docs-media.publishpress.com/publishpress-checklists/multisite-id-558d57eb0d.webp)

*Multisite Id*

### #2. Install the code snippet code into your functions.php file [#](#h-2-install-the-code-snippet-code-into-your-functions-php-file)

Add the following code to the *functions.php* file of the theme used on the main site in the network. This is the site you will use to configure the checklists.

It is important to note that the user roles you select on the settings field should be available on all the subsites that will receive the options.

```
add_action('updated_option', 'syncChecklistsToOtherSites', 10, 3);
function syncChecklistsToOtherSites($option, $oldValue, $value)
{
    if ($option !== 'publishpress_checklists_checklists_options') {
        return;
    }

    // Update this variable adding the site IDs where you want to copy the options to.
    $siteIdsToSync = [2, 3];

    try {
        $currentNetworkId = get_current_network_id();
        $value            = (object)$value;

        foreach ($siteIdsToSync as $siteId) {
            if ($siteId === $currentNetworkId) {
                continue;
            }

            switch_to_blog($siteId);
            update_option('publishpress_checklists_checklists_options', $value);
        }

        switch_to_blog($currentNetworkId);
    } catch (Exception $e) {
        error_log(
            sprintf(
                '%s: %s',
                __FUNCTION__,
                $e->getMessage()
            )
        );
    }
}
```

### #3. Customize the variable “$siteIdsToSync” in the code [#](#h-3-customize-the-variable-siteidstosync-in-the-code)

Update the value of the variable `$siteIdsToSync` in the code you pasted. Add the site IDs you selected.

### #4. Configure the checklists [#](#h-4-configure-the-checklists)

Now you can configure the checklists on the main site of your network. Visit the checklists on the subsites you selected. The settings should be synced each time you save the options on the main site.
