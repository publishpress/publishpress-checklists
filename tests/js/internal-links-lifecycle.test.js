/* eslint-env node */

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const repositoryRoot = path.resolve(__dirname, '../..');
const metaBoxPath = path.join(repositoryRoot, 'modules/checklists/assets/js/meta-box.js');
const internalLinksMarker = '/*----------  Internal Links ----------*/';
const externalLinksMarker = '/*----------  External Links ----------*/';

function readMetaBoxSource() {
  const revision = process.env.META_BOX_GIT_REVISION;

  if (revision) {
    return execFileSync('git', ['show', `${revision}:modules/checklists/assets/js/meta-box.js`], {
      cwd: repositoryRoot,
      encoding: 'utf8',
    });
  }

  return fs.readFileSync(metaBoxPath, 'utf8');
}

function runInternalLinksLifecycle(isInitialized) {
  const source = readMetaBoxSource();
  const start = source.indexOf(internalLinksMarker);
  const end = source.indexOf(externalLinksMarker, start);

  assert.notStrictEqual(start, -1, 'internal links section should exist');
  assert.notStrictEqual(end, -1, 'external links section should follow internal links section');

  const documentHandlers = {};
  const requirementStates = [];
  const editorHandlers = {};
  const document = {};
  const content = {
    value: '<p><a href="https://example.test/about">About</a></p>',
    val() {
      return this.value;
    },
    on() {},
  };
  const requirement = {
    length: 1,
    each(callback) {
      callback.call(requirement, requirement);
    },
    attr(name) {
      return name === 'id' ? 'pp-checklists-req-internal_links' : undefined;
    },
    trigger(eventName, state) {
      requirementStates.push({ eventName, state });
    },
  };

  function $(selector) {
    if (selector === document) {
      return {
        on(eventName, callback) {
          documentHandlers[eventName] = callback;
        },
      };
    }

    if (selector === '#content') {
      return content;
    }

    if (selector === '[id^="pp-checklists-req-internal_links"]') {
      return requirement;
    }

    if (selector === requirement) {
      return requirement;
    }

    return {
      length: 0,
      on() {},
    };
  }

  let readyCallback;
  const editor = {
    id: 'content',
    initialized: isInitialized,
    isHidden() {
      return false;
    },
    getContent() {
      return '<p><a href="https://example.test/about">About</a></p>';
    },
    on(eventName, callback) {
      editorHandlers[eventName] = (editorHandlers[eventName] || 0) + 1;
      editorHandlers[`${eventName}:callback`] = callback;
    },
    onInit: {
      add(callback) {
        if (isInitialized) {
          throw new Error('an already initialized editor must not wait for onInit');
        }

        readyCallback = callback;
      },
    },
  };

  const context = {
    $,
    document,
    PP_Checklists: {
      EVENT_TINYMCE_LOADED: 'pp-checklists:tinymce_loaded',
      EVENT_TIC: 'pp-checklists:tic',
      is_gutenberg_active: () => false,
      extract_internal_links: (value) => (value.indexOf('example.test') === -1 ? [] : ['link']),
      check_valid_quantity: (count, min, max) => count === 1 && min === 1 && max === 0,
      EVENT_UPDATE_REQUIREMENT_STATE: 'pp-checklists:update_requirement_state',
    },
    ppChecklists: {
      requirements: {
        internal_links: { value: [1, 0] },
      },
    },
    _: {
      debounce: (callback) => callback,
    },
    tinymce: { editors: { content: editor } },
    window: { location: { host: 'example.test' } },
    console,
  };

  vm.runInNewContext(source.slice(start, end), context, { filename: metaBoxPath });

  // The eager pass can run before PP_Checklists.init() binds the requirement
  // state event. Reproduce that stale-DOM seam by discarding the eager pass.
  requirementStates.length = 0;

  documentHandlers['pp-checklists:tinymce_loaded']({}, context.tinymce);
  if (isInitialized) {
    assert.deepStrictEqual(requirementStates, [
      {
        eventName: 'pp-checklists:update_requirement_state',
        state: true,
      },
    ]);
    requirementStates.length = 0;
  }

  documentHandlers['pp-checklists:tinymce_loaded']({}, context.tinymce);

  if (!isInitialized) {
    assert.strictEqual(typeof readyCallback, 'function');
    editor.initialized = true;
    readyCallback();
  }

  assert.strictEqual(editorHandlers['nodechange keyup'], 1);
  assert.deepStrictEqual(requirementStates, [
    {
      eventName: 'pp-checklists:update_requirement_state',
      state: true,
    },
  ]);

  editorHandlers['nodechange keyup:callback']({ type: 'keyup' });
  assert.strictEqual(requirementStates.length, 1);

  // Also verify the delayed-init fallback used when the eager ready update
  // happened before the requirement-state listener was installed.
  requirementStates.length = 0;
  documentHandlers['pp-checklists:tic']({});
  assert.deepStrictEqual(requirementStates, [
    {
      eventName: 'pp-checklists:update_requirement_state',
      state: true,
    },
  ]);
}

runInternalLinksLifecycle(true);
runInternalLinksLifecycle(false);
console.log('Internal links lifecycle regression passed');
