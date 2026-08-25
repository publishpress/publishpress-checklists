/**
 * @package PublishPress Checklists
 *
 * Block editor (Gutenberg) requirement evaluators.
 *
 * This module is the Real Time Collaboration (RTC) compatible replacement for
 * the DOM based evaluation engine that used to live in meta-box.js. Instead of
 * reading/writing the classic meta box markup, every requirement is evaluated
 * directly against the block editor state exposed by wp.data. Because no
 * classic meta box is registered for block editor post types anymore, WordPress
 * no longer disables real time collaboration for the post. See issue #1208.
 *
 * meta-box.js is still used, unchanged, for the Classic Editor.
 */

/* eslint-disable no-useless-escape */

const editor = () => wp.data.select('core/editor');

/**
 * Pure helpers ported verbatim from meta-box.js so both engines behave the
 * same. They only operate on strings, so they are safe to reuse here.
 */
export const helpers = {
    check_valid_quantity(count, min_value, max_value) {
        min_value = parseInt(min_value);
        max_value = parseInt(max_value);
        if (isNaN(min_value)) {
            min_value = 0;
        }
        if (isNaN(max_value)) {
            max_value = 0;
        }
        let is_valid = false;

        // Both same value = exact
        if (min_value === max_value) {
            is_valid = count === min_value;
        }

        // Min not empty, max empty or < min = only min
        if (min_value > 0 && max_value < min_value) {
            is_valid = count >= min_value;
        }

        // Min not empty, max not empty and > min = both min and max
        if (min_value > 0 && max_value > min_value) {
            is_valid = count >= min_value && count <= max_value;
        }

        // Min empty, max not empty and > min = only max
        if (min_value === 0 && max_value > min_value) {
            is_valid = count <= max_value;
        }

        return is_valid;
    },

    extract_internal_links(content, links = [], website = window.location.host) {
        let link;
        if (content) {
            content = content.replace(/<img[^>]*>/g, '');
            content = content.replace(/<a [^>]*href="([^"']*).*?<\/a>/g, ' $1 ');
            content = content.match(/(https?:\/\/(?:www\.|(?!www))[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})/gi);

            if (content) {
                for (link of content) {
                    if (link.match(/\.(jpeg|jpg|gif|png|svg)$/)) continue;
                    if (link.indexOf(website) < 0) continue;
                    links.push(link);
                }
            }
        }

        return links;
    },

    extract_external_links(content, links = [], website = window.location.host) {
        let link,
            match,
            regex = /<a.*?href=["\']([^"\']+)["\'].*?\>(.*?)\<\/a\>/gi;
        if (content) {
            while ((match = regex.exec(content)) !== null) {
                link = match[1];
                if (link.match(/\.(jpeg|jpg|gif|png|svg)$/)) continue;
                if (link.indexOf(website) > 0) continue;
                links.push(link);
            }
        }

        return links;
    },

    missing_alt_images(content, missing_alt = []) {
        let alt,
            regex = /<img[^>]*>/g;

        if (!Array.isArray(missing_alt)) {
            missing_alt = [];
        }

        if (content) {
            let imgTags = content.match(regex) || [];
            imgTags.forEach(function (imgTag) {
                alt = imgTag.match(/alt="([^"]*)"/);

                if (!alt || !alt[1].replace(/\s/g, '').length) {
                    missing_alt.push(imgTag);
                }
            });
        }

        return missing_alt;
    },

    get_image_alt_lengths(content) {
        let lengths = [];
        let regex = /<img[^>]+alt=(['"])(.*?)\1[^>]*>/gi;
        let match;

        while ((match = regex.exec(content)) !== null) {
            lengths.push(match[2].trim().length);
        }

        return lengths;
    },

    extract_links_from_content(content) {
        let linksIterator = content.matchAll(/(?:<a[^>]+href=['"])([^'"]+)(?:['"][^>]*>)/gi);

        let linkResult = linksIterator.next();
        let linksList = [];

        while (!linkResult.done) {
            linksList.push(linkResult.value[1]);
            linkResult = linksIterator.next();
        }

        return linksList;
    },

    is_valid_link(link) {
        if (link.startsWith('#')) {
            return true;
        }

        const linkWithoutFragment = link.split('#')[0];

        return linkWithoutFragment.match(
            /^(?:(#[-a-zA-Z0-9@:%._\+~#=]{0,256})|https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9-]{2,63}\b(?:[-a-zA-Z0-9()@;:%_\+.~#?&\/\/=!*'(),]*)|tel:\+?[0-9\-]+|mailto:[a-z0-9\-_\.]+@[a-z0-9\-_\.]+?[a-z0-9@\.\?=\s\%,\-&_;*]+)$/i,
        );
    },

    validate_links_format(content, invalid_links = []) {
        if (!content) {
            return [];
        }

        let linksList = helpers.extract_links_from_content(content);

        for (let i = 0; i < linksList.length; i++) {
            if (!helpers.is_valid_link(linksList[i])) {
                invalid_links.push(linksList[i]);
            }
        }

        return invalid_links;
    },

    hasFeaturedImage() {
        return editor().getEditedPostAttribute('featured_media') > 0;
    },

    getContent() {
        const content = editor().getEditedPostAttribute('content');
        return typeof content === 'undefined' ? '' : content;
    },
};

/**
 * Returns the alt/caption text of the current featured media, or '' when the
 * media object is not resolved yet. Reading the selector triggers wp.data's
 * resolver, so the panel's subscribe loop re-runs once the media is available.
 */
function getFeaturedMediaField(field) {
    const mediaId = editor().getEditedPostAttribute('featured_media');
    if (!mediaId) {
        return '';
    }

    const media = wp.data.select('core').getMedia(mediaId);
    if (!media || typeof media !== 'object') {
        return '';
    }

    if (field === 'caption') {
        if (media.caption) {
            if (typeof media.caption === 'object' && media.caption.raw !== undefined) {
                return media.caption.raw;
            }
            if (typeof media.caption === 'string') {
                return media.caption;
            }
        }
        return '';
    }

    return media[field] || '';
}

/**
 * Recomputes the label of a tags/categories requirement, appending the list of
 * missing/present terms, mirroring the behaviour of the old DOM engine.
 */
function buildTermLabel(config, reachedTerms, allTerms, useAllTermsForString) {
    if (config.has_editor_label) {
        return config.label;
    }

    const baseLabel = (config.label || '').replace(/:.*/, '');
    const source = useAllTermsForString ? allTerms : reachedTerms;
    const termsStr = source.map((el) => el.split('__')[1]).join(', ');

    return termsStr.length > 0 ? `${baseLabel}: ${termsStr} ` : `${baseLabel} `;
}

/**
 * Evaluators keyed by requirement type. Each receives the requirement config
 * object and returns either a boolean status or an object { status, label }.
 */
const evaluators = {
    featured_image() {
        return helpers.hasFeaturedImage();
    },

    featured_image_alt() {
        if (!helpers.hasFeaturedImage()) {
            return true;
        }
        return Boolean(getFeaturedMediaField('alt_text'));
    },

    featured_image_caption() {
        if (!helpers.hasFeaturedImage()) {
            return true;
        }
        return Boolean(getFeaturedMediaField('caption'));
    },

    tags_count(config) {
        if (!config.value) return config.status;
        const tags = editor().getEditedPostAttribute('tags');
        if (typeof tags === 'undefined' || tags === null) return config.status;
        return helpers.check_valid_quantity(tags.length, config.value[0], config.value[1]);
    },

    categories_count(config) {
        if (!config.value) return config.status;
        const categories = editor().getEditedPostAttribute('categories');
        if (typeof categories === 'undefined' || categories === null) return config.status;
        return helpers.check_valid_quantity(categories.length, config.value[0], config.value[1]);
    },

    required_tags(config) {
        const tags = editor().getEditedPostAttribute('tags') || [];
        const required = config.value || [];
        const missing = required.filter((value) => !tags.includes(Number(value.split('__')[0])));
        return {
            status: missing.length === 0,
            label: buildTermLabel(config, missing, required, false),
        };
    },

    prohibited_tags(config) {
        const tags = editor().getEditedPostAttribute('tags') || [];
        const prohibited = config.value || [];
        const present = prohibited.filter((value) => tags.includes(Number(value.split('__')[0])));
        return {
            status: present.length === 0,
            label: buildTermLabel(config, present, prohibited, true),
        };
    },

    required_categories(config) {
        const categories = editor().getEditedPostAttribute('categories') || [];
        const required = config.value || [];
        const missing = required.filter((value) => !categories.includes(Number(value.split('__')[0])));
        return {
            status: missing.length === 0,
            label: buildTermLabel(config, missing, required, false),
        };
    },

    prohibited_categories(config) {
        const categories = editor().getEditedPostAttribute('categories') || [];
        const prohibited = config.value || [];
        const present = prohibited.filter((value) => categories.includes(Number(value.split('__')[0])));
        return {
            status: present.length === 0,
            label: buildTermLabel(config, present, prohibited, true),
        };
    },

    filled_excerpt(config) {
        if (!config.value) return config.status;
        const text = editor().getEditedPostAttribute('excerpt') || '';
        return helpers.check_valid_quantity(text.length, config.value[0], config.value[1]);
    },

    title_count(config) {
        if (!config.value) return config.status;
        const title = wp.htmlEntities.decodeEntities(editor().getEditedPostAttribute('title') || '');
        return helpers.check_valid_quantity(title.length, config.value[0], config.value[1]);
    },

    words_count(config) {
        if (!config.value) return config.status;
        const count = new wp.utils.WordCounter().count(helpers.getContent());
        return helpers.check_valid_quantity(count, config.value[0], config.value[1]);
    },

    internal_links(config) {
        if (!config.value) return config.status;
        const count = helpers.extract_internal_links(helpers.getContent()).length;
        return helpers.check_valid_quantity(count, config.value[0], config.value[1]);
    },

    external_links(config) {
        if (!config.value) return config.status;
        const count = helpers.extract_external_links(helpers.getContent()).length;
        return helpers.check_valid_quantity(count, config.value[0], config.value[1]);
    },

    image_alt() {
        return helpers.missing_alt_images(helpers.getContent()).length === 0;
    },

    validate_links() {
        return helpers.validate_links_format(helpers.getContent()).length === 0;
    },

    image_alt_count(config) {
        if (!config.value) return config.status;
        const lengths = helpers.get_image_alt_lengths(helpers.getContent());
        return lengths.every((len) => helpers.check_valid_quantity(len, config.value[0], config.value[1]));
    },
};

/**
 * Taxonomy counter requirements have a dynamic type of
 * taxonomy_counter_hierarchical_<taxonomy> or
 * taxonomy_counter_non_hierarchical_<taxonomy>.
 */
function evaluateTaxonomyCounter(config) {
    if (!config.value) return config.status;

    const type = config.type || '';
    let taxonomy = '';
    let attribute = '';

    if (type.indexOf('taxonomy_counter_hierarchical_') === 0) {
        taxonomy = type.replace('taxonomy_counter_hierarchical_', '');
        const restBase = config.extra;
        attribute = restBase && restBase !== '' && restBase !== 'false' ? restBase : taxonomy;
    } else {
        taxonomy = type.replace('taxonomy_counter_non_hierarchical_', '');
        attribute = taxonomy;
    }

    const terms = editor().getEditedPostAttribute(attribute) || [];
    return helpers.check_valid_quantity(terms.length, config.value[0], config.value[1]);
}

/**
 * Evaluate a single requirement and return { status, label }.
 *
 * Custom, button-based and third-party requirement types are not auto
 * evaluated here: their status is user driven (custom items) or produced by
 * their own integration, so we keep the current status. Third parties (Pro,
 * Yoast SEO, Permalinks, etc.) can plug their own evaluator through the
 * `publishpress_checklists.evaluate_requirement` JS filter.
 *
 * @param {Object} config The requirement config, augmented with a live
 *                        `status` reflecting the current computed state.
 * @return {{status: boolean, label: (string|undefined)}}
 */
export function evaluateRequirement(config) {
    const type = config.type || '';
    let result;

    // User driven / externally driven requirements keep their status.
    const userDriven = config.is_custom || config.require_button;

    if (userDriven) {
        result = { status: !!config.status };
    } else if (typeof evaluators[type] === 'function') {
        result = evaluators[type](config);
    } else if (type.indexOf('taxonomy_counter_') === 0) {
        result = evaluateTaxonomyCounter(config);
    } else {
        // Unknown type: keep the server provided / current status.
        result = { status: !!config.status };
    }

    if (typeof result === 'boolean') {
        result = { status: result };
    }

    // Allow third parties to override the computed status/label.
    const filtered = wp.hooks.applyFilters(
        'publishpress_checklists.evaluate_requirement',
        result,
        config,
    );

    if (typeof filtered === 'boolean') {
        return { status: filtered };
    }

    return filtered && typeof filtered === 'object' ? filtered : result;
}

/**
 * Whether a requirement is evaluated by this React engine (panel-owned) rather
 * than by an external integration script (Yoast SEO, Permalinks, Pro, ...).
 *
 * Panel-owned requirements are the built-in types and the user-driven ones.
 * Everything else is handled through the compatibility bridge so third-party
 * scripts that still expect the classic markup keep working. See #1208.
 *
 * @param {Object} config
 * @return {boolean}
 */
export function isPanelOwned(config) {
    if (!config) {
        return false;
    }

    if (config.is_custom || config.require_button) {
        return true;
    }

    const type = config.type || '';

    if (typeof evaluators[type] === 'function') {
        return true;
    }

    return type.indexOf('taxonomy_counter_') === 0;
}
