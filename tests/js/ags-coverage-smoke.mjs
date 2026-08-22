import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const frontendListeners = new Map();
const classSelect = {value: 'Klasse 7.2', listeners: {}, addEventListener(type, handler) { this.listeners[type] = handler; }};
const weekdaySelect = {value: 'montag', listeners: {}, addEventListener(type, handler) { this.listeners[type] = handler; }};
const scope = {
  querySelector(selector) {
    if (selector === '[data-flz-ags-class-select]') return classSelect;
    if (selector === '[data-flz-ags-weekday-select]') return weekdaySelect;
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '[data-flz-ags-class-select]') return [classSelect];
    if (selector === '[data-flz-ags-weekday-select]') return [weekdaySelect];
    if (selector === '[data-flz-ags-filter-item]') return items;
    return [];
  }
};
const makeItem = (attributes, input = null) => ({
  attributes,
  hidden: false,
  closest: () => scope,
  getAttribute(name) { return this.attributes[name] ?? null; },
  querySelector: () => input
});
const availableInput = {disabled: false, checked: true};
const fullInput = {disabled: false, checked: false};
const items = [
  makeItem({'data-only-grade-7': '1', 'data-allowed-grades': '7', 'data-weekdays': 'montag', 'data-weekday': 'montag'}, availableInput),
  makeItem({'data-only-grade-7': '0', 'data-allowed-grades': '8, 9', 'data-weekdays': 'dienstag', 'data-weekday': 'dienstag', 'data-full': '1'}, fullInput),
  makeItem({'data-only-grade-7': '0', 'data-allowed-grades': ''})
];
const document = {
  addEventListener(type, handler) { frontendListeners.set(type, handler); },
  querySelectorAll(selector) { return selector === '.flz-ags' ? [scope] : []; }
};
const frontendContext = vm.createContext({document, console, String});
const frontendFile = path.join(root, 'assets/js/flz-ags.js');
vm.runInContext(fs.readFileSync(frontendFile, 'utf8'), frontendContext, {filename: frontendFile});
frontendListeners.get('DOMContentLoaded')();
assert.equal(items[0].hidden, false);
assert.equal(items[1].hidden, true);
assert.equal(items[2].hidden, true);

classSelect.value = '8.1';
weekdaySelect.value = 'dienstag';
classSelect.listeners.change();
assert.equal(items[0].hidden, true);
assert.equal(availableInput.checked, false);
assert.equal(items[1].hidden, false);
assert.equal(fullInput.disabled, true);

weekdaySelect.value = '';
weekdaySelect.listeners.change();
assert.equal(items[2].hidden, false);

class Wrapper {
  constructor(subject = {}) {
    this.subject = subject;
  }

  on(event, selector, handler) {
    adminHandlers.set(`${event}:${selector}`, handler);
    return this;
  }

  closest() {
    return this.subject.closestResult || this;
  }

  find(selector) {
    return this.subject.findResults?.[selector] || new Wrapper();
  }

  val(value) {
    if (arguments.length) {
      this.subject.value = value;
      return this;
    }
    return this.subject.value || '';
  }

  trigger() {
    this.subject.triggered = true;
    return this;
  }

  attr(name) {
    return this.subject.attributes?.[name];
  }
}

const adminHandlers = new Map();
const adminDocument = {};
const documentWrapper = new Wrapper(adminDocument);
const jquery = (subject) => subject === adminDocument ? documentWrapper : (subject instanceof Wrapper ? subject : new Wrapper(subject));
jquery.trim = (value) => String(value).trim();
jquery.each = (values, callback) => values.forEach((value, index) => callback(index, value));
const window = {
  flzAgsAdmin: {ajaxUrl: '/ajax', nonce: 'neutral', strings: {}},
  clearTimeout() {},
  setTimeout() { return 1; }
};
const adminContext = vm.createContext({window, document: adminDocument, jQuery: jquery, console, wp: {}});
const adminFile = path.join(root, 'assets/js/flz-ags-admin.js');
vm.runInContext(fs.readFileSync(adminFile, 'utf8'), adminContext, {filename: adminFile});
assert.ok(adminHandlers.size >= 7);

const imageInput = new Wrapper({value: 'vorher'});
const imageField = new Wrapper({findResults: {'[data-flz-ags-image-input]': imageInput}});
const clearButton = new Wrapper({closestResult: imageField});
let prevented = false;
adminHandlers.get('click:[data-flz-ags-clear-image]').call(clearButton, {preventDefault() { prevented = true; }});
assert.equal(prevented, true);
assert.equal(imageInput.subject.value, '');
assert.equal(imageInput.subject.triggered, true);
