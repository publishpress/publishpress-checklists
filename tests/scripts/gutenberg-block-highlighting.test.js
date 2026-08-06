'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
  path.resolve(__dirname, '../../modules/checklists/assets/js/gutenberg-block-highlighting.js'),
  'utf8',
);

function createCanvasDocument() {
  const state = {
    bodyChildren: [],
    documentListeners: [],
    headChildren: [],
    body: null,
    head: null,
  };

  const canvasDocument = {
    body: null,
    head: null,
    documentElement: {
      style: {
        setProperty() {},
      },
    },
    defaultView: {
      addEventListener() {},
      innerHeight: 800,
      innerWidth: 1280,
      scrollX: 0,
      scrollY: 0,
    },
    addEventListener(type, listener) {
      state.documentListeners.push({ type, listener });
    },
    createElement(tagName) {
      return {
        classList: {
          add() {},
          remove() {},
        },
        id: '',
        style: {},
        tagName,
        textContent: '',
      };
    },
    getElementById(id) {
      return state.bodyChildren.concat(state.headChildren).find((element) => element.id === id) || null;
    },
  };

  return {
    canvasDocument,
    state,
    setBody() {
      state.body = {
        appendChild(element) {
          state.bodyChildren.push(element);
        },
      };
      canvasDocument.body = state.body;
    },
    setHead() {
      state.head = {
        appendChild(element) {
          state.headChildren.push(element);
        },
      };
      canvasDocument.head = state.head;
    },
  };
}

function createHarness() {
  const canvas = createCanvasDocument();
  const frameListeners = new Map();
  const timers = [];
  const frame = {
    contentDocument: canvas.canvasDocument,
    addEventListener(type, listener) {
      const listeners = frameListeners.get(type) || [];
      listeners.push(listener);
      frameListeners.set(type, listeners);
    },
    removeEventListener(type, listener) {
      const listeners = frameListeners.get(type) || [];
      frameListeners.set(type, listeners.filter((current) => current !== listener));
    },
    dispatch(type) {
      (frameListeners.get(type) || []).slice().forEach((listener) => listener());
    },
  };
  const outerDocument = {
    documentElement: {
      style: {
        setProperty() {},
      },
    },
    querySelector(selector) {
      return selector === 'iframe[name="editor-canvas"]' ? frame : null;
    },
    querySelectorAll() {
      return [];
    },
  };
  const context = {
    Array,
    JSON,
    Math,
    Object,
    PP_Checklists: {
      is_gutenberg_active: () => true,
      missing_alt_images: () => [],
      validate_links_format: () => [],
      get_image_alt_lengths: () => [],
      check_valid_quantity: () => true,
    },
    clearTimeout(timer) {
      const index = timers.indexOf(timer);
      if (index !== -1) {
        timers.splice(index, 1);
      }
    },
    console,
    document: outerDocument,
    setTimeout(callback) {
      timers.push(callback);
      return callback;
    },
    wp: {
      data: {
        registerStore() {},
        select(storeName) {
          if (storeName === 'core/editor') {
            return {
              getEditedPostAttribute: () => undefined,
            };
          }

          return null;
        },
        subscribe() {},
      },
    },
  };

  context.window = context;
  vm.runInNewContext(source, context, {
    filename: 'gutenberg-block-highlighting.js',
  });

  // Ignore the module's initial delayed sync; each test controls its own sync.
  timers.length = 0;

  return {
    api: context.window.PP_Checklists_Block_Highlighting,
    canvas,
    frame,
    flushTimers() {
      while (timers.length > 0) {
        timers.shift()();
      }
    },
  };
}

test('waits for the editor canvas body before initializing tooltip behavior', () => {
  const harness = createHarness();
  harness.canvas.setHead();

  assert.doesNotThrow(() => harness.api.syncCurrentWarnings());
  assert.equal(harness.canvas.state.bodyChildren.length, 0);

  harness.canvas.setBody();
  harness.frame.dispatch('load');
  harness.flushTimers();

  assert.equal(harness.canvas.state.bodyChildren[0].id, 'pp-checklists-warning-tooltip');
});

test('skips style injection until the editor canvas head exists', () => {
  const harness = createHarness();
  harness.canvas.setBody();

  assert.doesNotThrow(() => harness.api.syncCurrentWarnings());
  assert.equal(harness.canvas.state.bodyChildren[0].id, 'pp-checklists-warning-tooltip');
});

test('rebinds tooltip behavior when Gutenberg replaces the canvas document', () => {
  const harness = createHarness();
  harness.canvas.setHead();
  harness.canvas.setBody();
  harness.api.syncCurrentWarnings();

  const replacement = createCanvasDocument();
  replacement.setHead();
  replacement.setBody();
  harness.frame.contentDocument = replacement.canvasDocument;

  assert.doesNotThrow(() => harness.api.syncCurrentWarnings());
  assert.equal(replacement.state.bodyChildren[0].id, 'pp-checklists-warning-tooltip');
});
