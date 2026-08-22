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

const modalListeners = new Map();
const makeClassList = (...classes) => {
  const values = new Set(classes);
  return {
    add(...names) { names.forEach((name) => values.add(name)); },
    remove(...names) { names.forEach((name) => values.delete(name)); },
    contains(name) { return values.has(name); },
    toggle(name, force) {
      if (force === false) values.delete(name);
      else if (force === true || !values.has(name)) values.add(name);
      else values.delete(name);
    }
  };
};
const makeNode = (...classes) => ({
  children: [],
  parentElement: null,
  classList: makeClassList(...classes),
  attributes: {},
  inert: false,
  append(...nodes) {
    nodes.forEach((node) => {
      node.parentElement = this;
      this.children.push(node);
    });
  },
  setAttribute(name, value) { this.attributes[name] = String(value); },
  getAttribute(name) { return this.attributes[name] ?? null; },
  removeAttribute(name) { delete this.attributes[name]; },
  querySelector() { return null; }
});
const modalBody = makeNode('body');
const modalBackground = makeNode('site-header');
const modalPage = makeNode('site-main');
const modalListHeader = makeNode('flz-ags-list-header');
const modalGrid = makeNode('flz-ags-grid');
const modalEntryOne = makeNode('flz-ags-course-entry');
const modalCardOne = makeNode('flz-ags-card');
const modalPanelOne = makeNode('flz-ui-floating-panel', 'flz-ags-registration-panel');
const modalTriggerOne = makeNode('flz-ui-floating-panel__trigger');
const modalDrawerOne = makeNode('flz-ui-floating-panel__drawer');
const modalEntryTwo = makeNode('flz-ags-course-entry');
const modalCardTwo = makeNode('flz-ags-card');
const modalPanelTwo = makeNode('flz-ui-floating-panel', 'flz-ags-registration-panel');
const modalTriggerTwo = makeNode('flz-ui-floating-panel__trigger');
const modalDrawerTwo = makeNode('flz-ui-floating-panel__drawer');
modalPanelOne.querySelector = (selector) => selector === '[data-flz-ui-floating-panel-open]' ? modalTriggerOne : (selector === '.flz-ui-floating-panel__drawer' ? modalDrawerOne : null);
modalPanelTwo.querySelector = (selector) => selector === '[data-flz-ui-floating-panel-open]' ? modalTriggerTwo : (selector === '.flz-ui-floating-panel__drawer' ? modalDrawerTwo : null);
modalTriggerOne.closest = () => modalPanelOne;
modalTriggerTwo.closest = () => modalPanelTwo;
modalPanelOne.append(modalTriggerOne, modalDrawerOne);
modalPanelTwo.append(modalTriggerTwo, modalDrawerTwo);
modalEntryOne.append(modalCardOne, modalPanelOne);
modalEntryTwo.append(modalCardTwo, modalPanelTwo);
modalGrid.append(modalEntryOne, modalEntryTwo);
modalPage.append(modalListHeader, modalGrid);
modalBody.append(modalBackground, modalPage);

const modalPanels = [modalPanelOne, modalPanelTwo];
const modalDocument = {
  body: modalBody,
  addEventListener(type, handler) {
    const handlers = modalListeners.get(type) || [];
    handlers.push(handler);
    modalListeners.set(type, handlers);
  },
  querySelectorAll(selector) {
    if (selector === '.flz-ags') return [];
    if (selector === '.flz-ags-registration-panel.is-open') return modalPanels.filter((panel) => panel.classList.contains('is-open'));
    if (selector === '[data-flz-ags-modal-inert]') {
      const result = [];
      const visit = (node) => {
        if (node.getAttribute('data-flz-ags-modal-inert') !== null) result.push(node);
        node.children.forEach(visit);
      };
      visit(modalBody);
      return result;
    }
    return [];
  }
};
const modalWindow = {setTimeout(handler) { handler(); }};
const modalContext = vm.createContext({document: modalDocument, window: modalWindow, console, String});
vm.runInContext(fs.readFileSync(frontendFile, 'utf8'), modalContext, {filename: frontendFile});
modalListeners.get('DOMContentLoaded').forEach((handler) => handler());

modalPanelOne.classList.add('is-open');
modalListeners.get('click').forEach((handler) => handler({target: modalTriggerOne}));
assert.equal(modalBackground.inert, true);
assert.equal(modalListHeader.inert, true);
assert.equal(modalCardOne.inert, true);
assert.equal(modalEntryTwo.inert, true);
assert.equal(modalPanelOne.inert, false);
assert.equal(modalDrawerOne.getAttribute('role'), 'dialog');
assert.equal(modalDrawerOne.getAttribute('aria-modal'), 'true');

modalPanelTwo.classList.add('is-open');
modalListeners.get('click').forEach((handler) => handler({target: modalTriggerTwo}));
assert.equal(modalPanelOne.classList.contains('is-open'), true);
assert.equal(modalPanelTwo.classList.contains('is-open'), false);
assert.equal(modalTriggerTwo.getAttribute('aria-expanded'), 'false');

modalPanelOne.classList.remove('is-open');
modalListeners.get('keydown').forEach((handler) => handler({key: 'Escape'}));
assert.equal(modalBackground.inert, false);
assert.equal(modalEntryTwo.inert, false);
assert.equal(modalDrawerOne.getAttribute('aria-modal'), null);

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
