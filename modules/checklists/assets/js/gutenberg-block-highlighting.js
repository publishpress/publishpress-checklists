(function (window, document) {
  'use strict';

  if (!window.PP_Checklists) {
    return;
  }

  const PP_Checklists_Block_Highlighting = {
    WARNING_BADGE_ICON: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28'%3E%3Ccircle cx='14' cy='14' r='12' fill='%23df0000' stroke='%23df0000' stroke-width='2'/%3E%3Cpath d='M14 7.5c.9 0 1.6.7 1.5 1.6l-.5 7.1a1 1 0 0 1-2 0l-.5-7.1c-.1-.9.6-1.6 1.5-1.6Z' fill='%23ffffff'/%3E%3Ccircle cx='14' cy='19.8' r='1.7' fill='%23ffffff'/%3E%3C/svg%3E")`,
    WARNING_STORE_NAME: 'pp-checklists/warnings',
    syncTimer: null,
    lastSyncSignature: null,
    isSubscribed: false,
    isStoreRegistered: false,
    isBlockFilterRegistered: false,
    isTooltipBehaviorBound: false,

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
        .block-editor-block-list__block.pp-checklists-has-warning:not(:has(> .block-editor-block-list__block-edit)),
        .block-editor-block-list__block.pp-checklists-has-warning > .block-editor-block-list__block-edit {
          outline: 2px dashed #df0000;
          outline-offset: 2px;
          position: relative;
        }

        .block-editor-block-list__block.pp-checklists-has-warning:not(:has(> .block-editor-block-list__block-edit))::before,
        .block-editor-block-list__block.pp-checklists-has-warning > .block-editor-block-list__block-edit::before {
          content: '';
          position: absolute;
          z-index: 30;
          top: 2px;
          right: 2px;
          width: 28px;
          height: 28px;
          background-image: var(--pp-checklists-warning-icon);
          background-repeat: no-repeat;
          background-position: center;
          background-size: 28px 28px;
          pointer-events: none;
          filter: drop-shadow(0 4px 8px rgba(223, 0, 0, 0.18));
        }

        #pp-checklists-warning-tooltip {
          position: absolute;
          z-index: 1000;
          max-width: 320px;
          padding: 8px 10px;
          border-radius: 6px;
          background: rgba(17, 17, 17, 0.95);
          color: #fff;
          font-size: 12px;
          line-height: 1.4;
          box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
          white-space: pre-line;
          pointer-events: none;
          opacity: 0;
          visibility: hidden;
          transform: translateY(-4px);
          transition: opacity 120ms ease, transform 120ms ease, visibility 120ms ease;
        }

        #pp-checklists-warning-tooltip.is-visible {
          opacity: 1;
          visibility: visible;
          transform: translateY(0);
        }

      `;

      canvasDocument.head.appendChild(style);
    },

    ensureEditorCanvasTooltipBehavior: function () {
      const canvasDocument = PP_Checklists_Block_Highlighting.getEditorCanvasDocument();

      if (!canvasDocument || PP_Checklists_Block_Highlighting.isTooltipBehaviorBound) {
        return;
      }

      let tooltipElement = canvasDocument.getElementById('pp-checklists-warning-tooltip');

      if (!tooltipElement) {
        tooltipElement = canvasDocument.createElement('div');
        tooltipElement.id = 'pp-checklists-warning-tooltip';
        canvasDocument.body.appendChild(tooltipElement);
      }

      let activeWarningElement = null;
      const win = canvasDocument.defaultView;

      const getWarningElement = function (node) {
        if (!node || !node.closest) {
          return null;
        }

        return node.closest('.block-editor-block-list__block.pp-checklists-has-warning');
      };

      const updateTooltipPosition = function () {
        if (!activeWarningElement) {
          return;
        }

        const rect = activeWarningElement.getBoundingClientRect();
        const tooltipWidth = Math.min(tooltipElement.offsetWidth || 320, 320);
        const badgeRight = rect.right + win.scrollX - 8;
        const rightSideLeft = badgeRight + 8;
        const maxLeft = Math.max(win.scrollX + win.innerWidth - tooltipWidth - 8, 8);
        let finalLeft = rightSideLeft;

        if (finalLeft > maxLeft) {
          // Fallback: place tooltip on the left when there is not enough space on the right.
          finalLeft = Math.max((rect.right + win.scrollX - 36) - 8 - tooltipWidth, 8);
        }

        finalLeft = Math.min(Math.max(finalLeft, 8), maxLeft);
        const preferredTop = rect.top + win.scrollY + 8;
        const maxTop = Math.max(win.scrollY + win.innerHeight - tooltipElement.offsetHeight - 8, 8);
        const clampedTop = Math.min(Math.max(preferredTop, 8), maxTop);

        tooltipElement.style.top = `${clampedTop}px`;
        tooltipElement.style.left = `${finalLeft}px`;
      };

      const showTooltip = function (warningElement) {
        const warningText = warningElement.getAttribute('title') || warningElement.getAttribute('data-warning-text') || '';

        if (!warningText) {
          return;
        }

        activeWarningElement = warningElement;
        tooltipElement.textContent = warningText;
        updateTooltipPosition();
        tooltipElement.classList.add('is-visible');
      };

      const hideTooltip = function () {
        activeWarningElement = null;
        tooltipElement.classList.remove('is-visible');
      };

      canvasDocument.addEventListener('mouseover', function (event) {
        const warningElement = getWarningElement(event.target);

        if (warningElement) {
          showTooltip(warningElement);
        }
      });

      canvasDocument.addEventListener('mouseout', function (event) {
        const warningElement = getWarningElement(event.target);
        const relatedWarningElement = getWarningElement(event.relatedTarget);

        if (warningElement && warningElement !== relatedWarningElement) {
          hideTooltip();
        }
      });

      canvasDocument.addEventListener('focusin', function (event) {
        const warningElement = getWarningElement(event.target);

        if (warningElement) {
          showTooltip(warningElement);
        }
      });

      canvasDocument.addEventListener('focusout', function (event) {
        const warningElement = getWarningElement(event.target);
        const relatedWarningElement = getWarningElement(event.relatedTarget);

        if (warningElement && warningElement !== relatedWarningElement) {
          hideTooltip();
        }
      });

      canvasDocument.addEventListener('scroll', updateTooltipPosition, true);
      win.addEventListener('resize', updateTooltipPosition);

      PP_Checklists_Block_Highlighting.isTooltipBehaviorBound = true;
    },

    registerWarningStore: function () {
      if (
        PP_Checklists_Block_Highlighting.isStoreRegistered ||
        !window.wp ||
        !wp.data ||
        !wp.data.registerStore
      ) {
        return;
      }

      const storeName = PP_Checklists_Block_Highlighting.WARNING_STORE_NAME;
      const defaultState = {
        warningsByBlock: {},
      };
      const actions = {
        setWarning: function (clientId, sourceKey, warningText) {
          return {
            type: 'SET_WARNING',
            clientId: clientId,
            sourceKey: sourceKey,
            warningText: warningText,
          };
        },
        resetWarnings: function () {
          return {
            type: 'RESET_WARNINGS',
          };
        },
      };
      const selectors = {
        getWarningsForBlock: function (state, clientId) {
          return state.warningsByBlock[clientId] || {};
        },
        getMergedWarningsForBlock: function (state, clientId) {
          return Object.values(state.warningsByBlock[clientId] || {});
        },
      };
      const reducer = function (state, action) {
        const nextState = state || defaultState;

        if (action.type === 'RESET_WARNINGS') {
          return defaultState;
        }

        if (action.type !== 'SET_WARNING') {
          return nextState;
        }

        if (!action.clientId || !action.sourceKey) {
          return nextState;
        }

        const warningText = action.warningText ? action.warningText.trim() : '';
        const currentBlockWarnings = Object.assign({}, nextState.warningsByBlock[action.clientId] || {});

        if (warningText) {
          currentBlockWarnings[action.sourceKey] = warningText;
        } else {
          delete currentBlockWarnings[action.sourceKey];
        }

        const warningsByBlock = Object.assign({}, nextState.warningsByBlock);

        if (Object.keys(currentBlockWarnings).length > 0) {
          warningsByBlock[action.clientId] = currentBlockWarnings;
        } else {
          delete warningsByBlock[action.clientId];
        }

        return {
          warningsByBlock: warningsByBlock,
        };
      };

      if (!wp.data.select(storeName)) {
        wp.data.registerStore(storeName, {
          reducer: reducer,
          actions: actions,
          selectors: selectors,
        });
      }

      PP_Checklists_Block_Highlighting.isStoreRegistered = true;
    },

    getWarningStoreSelect: function () {
      if (!window.wp || !wp.data || !wp.data.select) {
        return null;
      }

      return wp.data.select(PP_Checklists_Block_Highlighting.WARNING_STORE_NAME);
    },

    getWarningStoreDispatch: function () {
      if (!window.wp || !wp.data || !wp.data.dispatch) {
        return null;
      }

      return wp.data.dispatch(PP_Checklists_Block_Highlighting.WARNING_STORE_NAME);
    },

    registerBlockWarningFilter: function () {
      if (
        PP_Checklists_Block_Highlighting.isBlockFilterRegistered ||
        !window.wp ||
        !wp.hooks ||
        !wp.hooks.addFilter ||
        !wp.compose ||
        !wp.compose.createHigherOrderComponent ||
        !wp.element ||
        !wp.element.createElement ||
        !wp.data ||
        !wp.data.useSelect
      ) {
        return;
      }

      const createElement = wp.element.createElement;
      const useSelect = wp.data.useSelect;
      const createHigherOrderComponent = wp.compose.createHigherOrderComponent;

      const withChecklistWarnings = createHigherOrderComponent(function (BlockListBlock) {
        return function (props) {
          const warningState = useSelect(function (select) {
            const warningSelectors = select(PP_Checklists_Block_Highlighting.WARNING_STORE_NAME);

            if (!warningSelectors || !warningSelectors.getMergedWarningsForBlock || !props.clientId) {
              return {
                hasWarning: false,
                mergedWarningText: '',
                mergedWarningTitle: '',
              };
            }

            const mergedWarnings = warningSelectors.getMergedWarningsForBlock(props.clientId);

            return {
              hasWarning: mergedWarnings.length > 0,
              mergedWarningText: mergedWarnings.join(' | '),
              mergedWarningTitle: mergedWarnings.join('\n'),
            };
          }, [props.clientId]);
          const wrapperProps = Object.assign({}, props.wrapperProps || {});
          const classTokens = (props.className || '').split(/\s+/).filter(Boolean).filter(function (className) {
            return className !== 'pp-checklists-has-warning';
          });

          if (warningState.hasWarning) {
            classTokens.push('pp-checklists-has-warning');
            wrapperProps['data-warning'] = 'true';
            wrapperProps['data-warning-text'] = warningState.mergedWarningText;
            wrapperProps.title = warningState.mergedWarningTitle;
            wrapperProps['aria-label'] = warningState.mergedWarningTitle;
          } else {
            delete wrapperProps['data-warning'];
            delete wrapperProps['data-warning-text'];
            delete wrapperProps.title;
            delete wrapperProps['aria-label'];
          }

          return createElement(BlockListBlock, Object.assign({}, props, {
            className: classTokens.join(' '),
            wrapperProps: wrapperProps,
          }));
        };
      }, 'withChecklistWarnings');

      wp.hooks.addFilter(
        'editor.BlockListBlock',
        'pp-checklists/with-warning-highlight',
        withChecklistWarnings,
      );

      PP_Checklists_Block_Highlighting.isBlockFilterRegistered = true;
    },

    hasChosenImage: function (block) {
      if (!block || block.name !== 'core/image') {
        return false;
      }

      const attributes = block.attributes || {};

      return Boolean(attributes.id || attributes.url);
    },

    updateBlockWarningState: function (clientId, sourceKey, hasWarning, warningText) {
      const warningStore = PP_Checklists_Block_Highlighting.getWarningStoreDispatch();
      const warningSelectors = PP_Checklists_Block_Highlighting.getWarningStoreSelect();
      const normalizedText = hasWarning ? (warningText || '') : '';

      if (!warningStore || !warningStore.setWarning) {
        return;
      }

      if (warningSelectors && warningSelectors.getWarningsForBlock) {
        const currentWarnings = warningSelectors.getWarningsForBlock(clientId) || {};
        const currentText = currentWarnings[sourceKey] || '';

        if (currentText === normalizedText) {
          return;
        }
      }

      warningStore.setWarning(clientId, sourceKey, normalizedText);
    },

    syncListViewWarningState: function () {
      const warningSelectors = PP_Checklists_Block_Highlighting.getWarningStoreSelect();
      const listViewElements = document.querySelectorAll(
        '.block-editor-list-view-leaf[data-block], .block-editor-list-view-tree [data-block], .block-editor-list-view [data-block]',
      );

      if (!warningSelectors || !warningSelectors.getMergedWarningsForBlock || !listViewElements.length) {
        return;
      }

      listViewElements.forEach(function (element) {
        const clientId = element.getAttribute('data-block');
        const mergedWarnings = warningSelectors.getMergedWarningsForBlock(clientId);

        if (mergedWarnings.length > 0) {
          element.setAttribute('data-warning', 'true');
          element.setAttribute('data-warning-text', mergedWarnings.join('\n'));
          return;
        }

        element.setAttribute('data-warning', 'false');
        element.removeAttribute('data-warning-text');
      });
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

    clearWarningsForSources: function (sourceKeys) {
      const keys = Array.isArray(sourceKeys) ? sourceKeys.filter(Boolean) : [];

      if (!keys.length) {
        return;
      }

      PP_Checklists_Block_Highlighting.getAllBlocks().forEach(function (block) {
        keys.forEach(function (sourceKey) {
          PP_Checklists_Block_Highlighting.updateBlockWarningState(block.clientId, sourceKey, false, '');
        });
      });
    },

    getImageAltCountSourceKeys: function () {
      return Array.from(document.querySelectorAll('[id^="pp-checklists-req-image_alt_count"]')).map(function (element) {
        return element.id.replace('pp-checklists-req-', '');
      });
    },

    syncCurrentWarnings: function () {
      if (!window.PP_Checklists || !window.PP_Checklists.is_gutenberg_active()) {
        return;
      }

      PP_Checklists_Block_Highlighting.ensureWarningIconVariable(document);
      PP_Checklists_Block_Highlighting.ensureEditorCanvasWarningStyles();
      PP_Checklists_Block_Highlighting.ensureEditorCanvasTooltipBehavior();

      PP_Checklists_Block_Highlighting.clearWarningsForSources(
        ['image_alt', 'validate_links'].concat(PP_Checklists_Block_Highlighting.getImageAltCountSourceKeys())
      );
      PP_Checklists_Block_Highlighting.syncImageAltWarnings();
      PP_Checklists_Block_Highlighting.syncInvalidLinkWarnings();
      PP_Checklists_Block_Highlighting.syncImageAltCountWarnings();
      PP_Checklists_Block_Highlighting.syncListViewWarningState();
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

      return [content || '', blockOrder].join('::');
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
  PP_Checklists_Block_Highlighting.registerWarningStore();
  PP_Checklists_Block_Highlighting.registerBlockWarningFilter();
  PP_Checklists_Block_Highlighting.ensureWarningIconVariable(document);
  PP_Checklists_Block_Highlighting.setupRealtimeSync();
  PP_Checklists_Block_Highlighting.queueSyncCurrentWarnings();
}(window, document));
