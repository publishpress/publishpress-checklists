(function (window, document) {
  'use strict';

  if (!window.PP_Checklists) {
    return;
  }

  const PP_Checklists_Block_Highlighting = {
    WARNING_BADGE_ICON: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28'%3E%3Ccircle cx='14' cy='14' r='12' fill='%23df0000' stroke='%23df0000' stroke-width='2'/%3E%3Cpath d='M14 7.5c.9 0 1.6.7 1.5 1.6l-.5 7.1a1 1 0 0 1-2 0l-.5-7.1c-.1-.9.6-1.6 1.5-1.6Z' fill='%23ffffff'/%3E%3Ccircle cx='14' cy='19.8' r='1.7' fill='%23ffffff'/%3E%3C/svg%3E")`,
    activeWarnings: {},
    syncTimer: null,
    lastSyncSignature: null,
    isSubscribed: false,

    getAllBlocks: function () {
      if (!window.PP_Checklists.is_gutenberg_active() || !wp.data.select('core/block-editor')) {
        return [];
      }

      const flattenBlocks = function (blocks) {
        return (blocks || []).reduce(function (allBlocks, block) {
          allBlocks.push(block);

          if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
            return allBlocks.concat(flattenBlocks(block.innerBlocks));
          }

          return allBlocks;
        }, []);
      };

      return flattenBlocks(wp.data.select('core/block-editor').getBlocks());
    },

    getEditorCanvasDocument: function () {
      const canvasFrame = document.querySelector('iframe[name="editor-canvas"]');

      if (!canvasFrame || !canvasFrame.contentDocument) {
        return null;
      }

      return canvasFrame.contentDocument;
    },

    ensureWarningIconVariable: function (targetDocument) {
      if (!targetDocument || !targetDocument.documentElement) {
        return;
      }

      targetDocument.documentElement.style.setProperty(
        '--pp-checklists-warning-icon',
        PP_Checklists_Block_Highlighting.WARNING_BADGE_ICON,
      );
    },

    ensureEditorCanvasWarningStyles: function () {
      const canvasDocument = PP_Checklists_Block_Highlighting.getEditorCanvasDocument();

      if (!canvasDocument || canvasDocument.getElementById('pp-checklists-warning-styles')) {
        return;
      }

      PP_Checklists_Block_Highlighting.ensureWarningIconVariable(canvasDocument);

      const style = canvasDocument.createElement('style');
      style.id = 'pp-checklists-warning-styles';
      style.textContent = `
        .block-editor-block-list__block.pp-checklists-has-warning {
          outline: 2px solid #df0000;
          outline-offset: 2px;
          position: relative;
        }

        .block-editor-block-list__block .pp-checklists-warning-badge {
          position: absolute;
          z-index: 30;
          width: 28px;
          height: 28px;
          padding: 0;
          border: 0;
          background: transparent;
          background-image: var(--pp-checklists-warning-icon);
          background-repeat: no-repeat;
          background-position: center;
          background-size: 28px 28px;
          box-sizing: border-box;
          pointer-events: auto;
          cursor: help;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          filter: drop-shadow(0 4px 8px rgba(223, 0, 0, 0.18));
        }

        .block-editor-block-list__block .pp-checklists-warning-tooltip {
          position: absolute;
          top: 42px;
          left: 10px;
          z-index: 31;
          max-width: 260px;
          padding: 8px 10px;
          border-radius: 6px;
          background: rgba(17, 17, 17, 0.95);
          color: #fff;
          font-size: 12px;
          line-height: 1.4;
          box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
          opacity: 0;
          visibility: hidden;
          transform: translateY(-4px);
          transition: opacity 120ms ease, transform 120ms ease, visibility 120ms ease;
          pointer-events: none;
          white-space: normal;
        }

        .block-editor-block-list__block .pp-checklists-warning-tooltip ul {
          margin: 0;
          padding-left: 16px;
        }

        .block-editor-block-list__block .pp-checklists-warning-tooltip li + li {
          margin-top: 4px;
        }

        .block-editor-block-list__block .pp-checklists-warning-badge:hover + .pp-checklists-warning-tooltip,
        .block-editor-block-list__block .pp-checklists-warning-badge:focus + .pp-checklists-warning-tooltip,
        .block-editor-block-list__block.pp-checklists-has-warning.is-selected .pp-checklists-warning-tooltip {
          opacity: 1;
          visibility: visible;
          transform: translateY(0);
        }
      `;

      canvasDocument.head.appendChild(style);
    },

    updateCanvasWarningIndicator: function (canvasElement, warningTexts) {
      const ownerDocument = canvasElement.ownerDocument;
      let badgeElement = canvasElement.querySelector(':scope > .pp-checklists-warning-badge');
      let tooltipElement = canvasElement.querySelector(':scope > .pp-checklists-warning-tooltip');
      const tooltipLines = Array.isArray(warningTexts) ? warningTexts : [warningTexts];
      const tooltipText = tooltipLines.join('\n');

      if (!badgeElement) {
        badgeElement = ownerDocument.createElement('button');
        badgeElement.type = 'button';
        badgeElement.className = 'pp-checklists-warning-badge';
        canvasElement.prepend(badgeElement);
      }

      if (!tooltipElement) {
        tooltipElement = ownerDocument.createElement('div');
        tooltipElement.className = 'pp-checklists-warning-tooltip';
        canvasElement.insertBefore(tooltipElement, badgeElement.nextSibling);
      }

      badgeElement.setAttribute('aria-label', tooltipText);
      badgeElement.setAttribute('title', tooltipText);
      tooltipElement.innerHTML = '';

      if (tooltipLines.length === 1) {
        tooltipElement.textContent = tooltipLines[0];
        return;
      }

      const listElement = ownerDocument.createElement('ul');

      tooltipLines.forEach(function (warningText) {
        const itemElement = ownerDocument.createElement('li');
        itemElement.textContent = warningText;
        listElement.appendChild(itemElement);
      });

      tooltipElement.appendChild(listElement);
    },

    hasChosenImage: function (block) {
      if (!block || block.name !== 'core/image') {
        return false;
      }

      const attributes = block.attributes || {};

      return Boolean(attributes.id || attributes.url);
    },

    updateBlockWarningState: function (clientId, sourceKey, hasWarning, warningText) {
      const trimmedWarningText = warningText ? warningText.trim() : '';
      const canvasDocument = PP_Checklists_Block_Highlighting.getEditorCanvasDocument();
      const listViewElement = document.querySelector(
        `.block-editor-list-view-leaf[data-block="${clientId}"], ` +
          `.block-editor-list-view-tree [data-block="${clientId}"], ` +
          `.block-editor-list-view [data-block="${clientId}"]`,
      );
      const canvasElement = canvasDocument
        ? canvasDocument.querySelector(`.block-editor-block-list__block[data-block="${clientId}"]`)
        : document.querySelector(`.block-editor-block-list__block[data-block="${clientId}"]`);
      const currentWarnings = PP_Checklists_Block_Highlighting.activeWarnings[clientId] || {};

      PP_Checklists_Block_Highlighting.ensureWarningIconVariable(document);

      if (hasWarning && trimmedWarningText) {
        currentWarnings[sourceKey] = trimmedWarningText;
      } else {
        delete currentWarnings[sourceKey];
      }

      if (Object.keys(currentWarnings).length > 0) {
        PP_Checklists_Block_Highlighting.activeWarnings[clientId] = currentWarnings;
      } else {
        delete PP_Checklists_Block_Highlighting.activeWarnings[clientId];
      }

      const mergedWarnings = Object.values(PP_Checklists_Block_Highlighting.activeWarnings[clientId] || {});
      const mergedWarningText = mergedWarnings.join(' | ');
      const mergedWarningTitle = mergedWarnings.join('\n');
      const warningState = mergedWarnings.length > 0 ? 'true' : 'false';

      if (listViewElement) {
        listViewElement.setAttribute('data-warning', warningState);
        if (mergedWarnings.length > 0) {
          listViewElement.setAttribute('data-warning-text', mergedWarningTitle);
        } else {
          listViewElement.removeAttribute('data-warning-text');
        }
      }

      if (!canvasElement) {
        return;
      }

      PP_Checklists_Block_Highlighting.ensureEditorCanvasWarningStyles();
      canvasElement.setAttribute('data-warning', warningState);
      canvasElement.classList.toggle('pp-checklists-has-warning', mergedWarnings.length > 0);

      if (mergedWarnings.length > 0) {
        canvasElement.setAttribute('data-warning-text', mergedWarningText);
        PP_Checklists_Block_Highlighting.updateCanvasWarningIndicator(canvasElement, mergedWarnings);
        return;
      }

      canvasElement.removeAttribute('data-warning-text');

      const badgeElement = canvasElement.querySelector(':scope > .pp-checklists-warning-badge');
      const tooltipElement = canvasElement.querySelector(':scope > .pp-checklists-warning-tooltip');

      if (badgeElement) {
        badgeElement.remove();
      }

      if (tooltipElement) {
        tooltipElement.remove();
      }
    },

    syncImageAltWarnings: function () {
      const content = wp.data.select('core/editor').getEditedPostAttribute('content');
      const requirementElement = document.querySelector('#pp-checklists-req-image_alt');

      if (typeof content === 'undefined' || !requirementElement) {
        return;
      }

      const missingAltImages = window.PP_Checklists.missing_alt_images(content, []);
      const warningText = requirementElement.textContent.trim();
      const imageBlocks = PP_Checklists_Block_Highlighting.getAllBlocks().filter((block) => block.name === 'core/image');

      imageBlocks.forEach(function (block) {
        if (!PP_Checklists_Block_Highlighting.hasChosenImage(block)) {
          PP_Checklists_Block_Highlighting.updateBlockWarningState(block.clientId, 'image_alt', false, warningText);
          return;
        }

        const hasWarning = missingAltImages.some(function (html) {
          return html.includes(block.attributes.id) || html.includes(block.attributes.url);
        });

        PP_Checklists_Block_Highlighting.updateBlockWarningState(block.clientId, 'image_alt', hasWarning, warningText);
      });
    },

    syncInvalidLinkWarnings: function () {
      const content = wp.data.select('core/editor').getEditedPostAttribute('content');
      const requirementElement = document.querySelector('#pp-checklists-req-validate_links');

      if (typeof content === 'undefined' || !requirementElement) {
        return;
      }

      const invalidLinks = window.PP_Checklists.validate_links_format(content);
      const warningText = requirementElement.textContent.trim();

      PP_Checklists_Block_Highlighting.getAllBlocks().forEach(function (block) {
        const blockContent = JSON.stringify(block.attributes || {});
        const hasWarning = invalidLinks.some(function (link) {
          return blockContent.includes(link);
        });

        PP_Checklists_Block_Highlighting.updateBlockWarningState(block.clientId, 'validate_links', hasWarning, warningText);
      });
    },

    syncImageAltCountWarnings: function () {
      const content = wp.data.select('core/editor').getEditedPostAttribute('content');
      const requirementElements = Array.from(document.querySelectorAll('[id^="pp-checklists-req-image_alt_count"]'));

      if (typeof content === 'undefined' || requirementElements.length === 0) {
        return;
      }

      const altLengths = window.PP_Checklists.get_image_alt_lengths(content);
      const imageBlocks = PP_Checklists_Block_Highlighting.getAllBlocks().filter((block) => block.name === 'core/image');

      requirementElements.forEach(function (element) {
        const requirementId = element.id.replace('pp-checklists-req-', '');
        const config = (typeof ppChecklists !== 'undefined' && ppChecklists.requirements[requirementId])
          ? ppChecklists.requirements[requirementId]
          : (typeof ppChecklists !== 'undefined' && ppChecklists.requirements.image_alt_count)
            ? ppChecklists.requirements.image_alt_count
            : null;

        if (!config || !config.value) {
          return;
        }

        const min = parseInt(config.value[0]);
        const max = parseInt(config.value[1]);
        const warningText = element.textContent.trim();

        imageBlocks.forEach(function (block, index) {
          if (!PP_Checklists_Block_Highlighting.hasChosenImage(block)) {
            PP_Checklists_Block_Highlighting.updateBlockWarningState(block.clientId, requirementId, false, warningText);
            return;
          }

          const altLength = typeof altLengths[index] === 'number'
            ? altLengths[index]
            : (block.attributes.alt ? block.attributes.alt.length : 0);
          const hasWarning = !window.PP_Checklists.check_valid_quantity(altLength, min, max);

          PP_Checklists_Block_Highlighting.updateBlockWarningState(block.clientId, requirementId, hasWarning, warningText);
        });
      });
    },

    syncCurrentWarnings: function () {
      if (!window.PP_Checklists || !window.PP_Checklists.is_gutenberg_active()) {
        return;
      }

      PP_Checklists_Block_Highlighting.syncImageAltWarnings();
      PP_Checklists_Block_Highlighting.syncInvalidLinkWarnings();
      PP_Checklists_Block_Highlighting.syncImageAltCountWarnings();
    },

    queueSyncCurrentWarnings: function () {
      if (PP_Checklists_Block_Highlighting.syncTimer) {
        window.clearTimeout(PP_Checklists_Block_Highlighting.syncTimer);
      }

      PP_Checklists_Block_Highlighting.syncTimer = window.setTimeout(function () {
        PP_Checklists_Block_Highlighting.syncTimer = null;
        PP_Checklists_Block_Highlighting.syncCurrentWarnings();
      }, 250);
    },

    getSyncSignature: function () {
      if (!window.PP_Checklists || !window.PP_Checklists.is_gutenberg_active()) {
        return '';
      }

      const editor = wp.data.select('core/editor');
      const blockEditor = wp.data.select('core/block-editor');
      const content = editor ? editor.getEditedPostAttribute('content') : '';
      const blockOrder = blockEditor ? blockEditor.getClientIdsWithDescendants().join(',') : '';
      const selectedBlockId = blockEditor ? blockEditor.getSelectedBlockClientId() : '';

      return [content || '', blockOrder, selectedBlockId || ''].join('::');
    },

    setupRealtimeSync: function () {
      if (PP_Checklists_Block_Highlighting.isSubscribed || !window.wp || !wp.data || !wp.data.subscribe) {
        return;
      }

      PP_Checklists_Block_Highlighting.isSubscribed = true;

      wp.data.subscribe(function () {
        const nextSignature = PP_Checklists_Block_Highlighting.getSyncSignature();

        if (nextSignature === PP_Checklists_Block_Highlighting.lastSyncSignature) {
          return;
        }

        PP_Checklists_Block_Highlighting.lastSyncSignature = nextSignature;
        PP_Checklists_Block_Highlighting.queueSyncCurrentWarnings();
      });
    },
  };

  window.PP_Checklists_Block_Highlighting = PP_Checklists_Block_Highlighting;
  PP_Checklists_Block_Highlighting.ensureWarningIconVariable(document);
  PP_Checklists_Block_Highlighting.setupRealtimeSync();
  PP_Checklists_Block_Highlighting.queueSyncCurrentWarnings();
}(window, document));
