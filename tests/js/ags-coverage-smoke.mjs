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
  readyState: 'loading',
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
  readyState: 'loading',
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

const makeControl = (value = '') => ({
  value,
  listeners: {},
  addEventListener(type, handler) { this.listeners[type] = handler; }
});
const discoveryControls = {
  className: makeControl(''),
  category: makeControl(''),
  weekday: makeControl(''),
  leader: makeControl(''),
  search: makeControl(''),
  sort: makeControl('default')
};
const discoveryStatus = {textContent: ''};
const discoveryNoResults = {hidden: true};
const discoveryContainer = {
  children: [],
  appendChild(item) {
    this.children = this.children.filter((child) => child !== item);
    this.children.push(item);
  }
};
let discoveryScope;
const makeDiscoveryItem = (attributes) => ({
  attributes,
  hidden: false,
  closest: () => discoveryScope,
  getAttribute(name) { return this.attributes[name] ?? null; },
  querySelector() { return null; }
});
const discoveryItems = [
  makeDiscoveryItem({'data-only-grade-7': '0', 'data-allowed-grades': '8', 'data-category': 'Technik', 'data-weekdays': '2', 'data-leader': 'Berta Blau', 'data-search': 'Robotik Berta Blau Technik Dienstag', 'data-sort-default': '20', 'data-sort-title': 'Robotik', 'data-sort-category': 'Technik', 'data-sort-grade': '8', 'data-sort-leader': 'Berta Blau', 'data-sort-weekday': '2'}),
  makeDiscoveryItem({'data-only-grade-7': '1', 'data-allowed-grades': '7', 'data-category': 'Kunst', 'data-weekdays': '1', 'data-leader': 'Änne Adler', 'data-search': 'Kunst Änne Adler Montag', 'data-sort-default': '10', 'data-sort-title': 'Kunst', 'data-sort-category': 'Kunst', 'data-sort-grade': '7', 'data-sort-leader': 'Änne Adler', 'data-sort-weekday': '1'}),
  makeDiscoveryItem({'data-only-grade-7': '0', 'data-allowed-grades': '', 'data-category': 'Bühne', 'data-weekdays': '3', 'data-leader': 'Berta Blau', 'data-search': 'Theater Berta Blau Mittwoch', 'data-sort-default': '30', 'data-sort-title': 'Theater', 'data-sort-category': 'Bühne', 'data-sort-grade': '0', 'data-sort-leader': 'Berta Blau', 'data-sort-weekday': '3'})
];
discoveryContainer.children = discoveryItems.slice();
discoveryScope = {
  querySelector(selector) {
    return {
      '[data-flz-ags-class-select]': discoveryControls.className,
      '[data-flz-ags-category-select]': discoveryControls.category,
      '[data-flz-ags-weekday-select]': discoveryControls.weekday,
      '[data-flz-ags-leader-select]': discoveryControls.leader,
      '[data-flz-ags-search-input]': discoveryControls.search,
      '[data-flz-ags-sort-select]': discoveryControls.sort,
      '[data-flz-ags-items]': discoveryContainer,
      '[data-flz-ags-results-status]': discoveryStatus,
      '[data-flz-ags-no-results]': discoveryNoResults
    }[selector] || null;
  },
  querySelectorAll(selector) {
    if (selector === '[data-flz-ags-filter-item]') return discoveryItems;
    const single = this.querySelector(selector);
    return single ? [single] : [];
  }
};
const discoveryListeners = new Map();
const discoveryDocument = {
  readyState: 'loading',
  addEventListener(type, handler) { discoveryListeners.set(type, handler); },
  querySelectorAll(selector) {
    if (selector === '.flz-ags') return [discoveryScope];
    return [];
  }
};
const discoveryContext = vm.createContext({document: discoveryDocument, console, String});
vm.runInContext(fs.readFileSync(frontendFile, 'utf8'), discoveryContext, {filename: frontendFile});
discoveryListeners.get('DOMContentLoaded')();
assert.deepEqual(discoveryContainer.children, [discoveryItems[1], discoveryItems[0], discoveryItems[2]]);
assert.equal(discoveryStatus.textContent, '3 AGs angezeigt.');

discoveryControls.className.value = '8';
discoveryControls.weekday.value = '2';
discoveryControls.leader.value = 'Berta Blau';
discoveryControls.search.value = 'robotik';
discoveryControls.search.listeners.input();
assert.equal(discoveryItems[0].hidden, false);
assert.equal(discoveryItems[1].hidden, true);
assert.equal(discoveryItems[2].hidden, true);
assert.equal(discoveryStatus.textContent, '1 AG angezeigt.');

discoveryControls.search.value = 'nicht vorhanden';
discoveryControls.search.listeners.input();
assert.equal(discoveryNoResults.hidden, false);

discoveryControls.className.value = '';
discoveryControls.weekday.value = '';
discoveryControls.leader.value = '';
discoveryControls.search.value = '';
discoveryControls.sort.value = 'title';
discoveryControls.sort.listeners.change();
assert.deepEqual(discoveryContainer.children, [discoveryItems[1], discoveryItems[0], discoveryItems[2]]);

discoveryControls.search.value = 'anne';
discoveryControls.search.listeners.input();
assert.equal(discoveryItems[1].hidden, false);
assert.equal(discoveryItems[0].hidden, true);

discoveryControls.search.value = '';
discoveryControls.sort.value = 'untrusted-column';
discoveryControls.sort.listeners.change();
assert.deepEqual(discoveryContainer.children, [discoveryItems[1], discoveryItems[0], discoveryItems[2]]);

discoveryControls.category.value = 'Technik';
discoveryControls.category.listeners.change();
assert.equal(discoveryItems[0].hidden, false);
assert.equal(discoveryItems[1].hidden, true);

discoveryControls.category.value = '';
discoveryControls.sort.value = 'category';
discoveryControls.sort.listeners.change();
assert.deepEqual(discoveryContainer.children, [discoveryItems[2], discoveryItems[1], discoveryItems[0]]);

discoveryStatus.textContent = 'nicht initialisiert';
const lateFrontendListeners = new Map();
const lateFrontendDocument = {
  readyState: 'complete',
  addEventListener(type, handler) { lateFrontendListeners.set(type, handler); },
  querySelectorAll(selector) {
    if (selector === '.flz-ags') return [discoveryScope];
    return [];
  }
};
const lateFrontendContext = vm.createContext({document: lateFrontendDocument, console, String});
vm.runInContext(fs.readFileSync(frontendFile, 'utf8'), lateFrontendContext, {filename: frontendFile});
assert.equal(discoveryStatus.textContent, '3 AGs angezeigt.');
assert.equal(lateFrontendListeners.has('DOMContentLoaded'), false);

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

const adminListControls = {
  search: makeControl(''),
  category: makeControl(''),
  grade: makeControl(''),
  leader: makeControl(''),
  weekday: makeControl('')
};
const makeAdminSortButton = (sortKey) => {
  const header = {
    attributes: {'aria-sort': 'none'},
    setAttribute(name, value) { this.attributes[name] = String(value); }
  };
  return {
    attributes: {'data-flz-ags-admin-sort-button': sortKey},
    listeners: {},
    header,
    addEventListener(type, handler) { this.listeners[type] = handler; },
    closest(selector) { return selector === 'th' ? header : null; },
    getAttribute(name) { return this.attributes[name] ?? null; },
    setAttribute(name, value) { this.attributes[name] = String(value); }
  };
};
const adminSortButtons = [
  makeAdminSortButton('title'),
  makeAdminSortButton('category'),
  makeAdminSortButton('grade'),
  makeAdminSortButton('leader'),
  makeAdminSortButton('weekday'),
  makeAdminSortButton('untrusted-column')
];
const adminStatus = {textContent: ''};
const adminNoResults = {hidden: true};
const makeAdminDetails = (id, hidden = true) => ({
  id,
  hidden,
  attributes: {},
  setAttribute(name, value) { this.attributes[name] = String(value); },
  getAttribute(name) { return this.attributes[name] ?? null; },
  removeAttribute(name) { delete this.attributes[name]; }
});
const makeAdminItem = (id, attributes) => ({
  id,
  attributes,
  hidden: false,
  getAttribute(name) { return this.attributes[name] ?? null; }
});
const adminItems = [
  makeAdminItem('admin-robotik', {'data-only-grade-7': '0', 'data-allowed-grades': '8', 'data-category': 'Technik', 'data-weekdays': '2', 'data-leader': 'Berta Blau', 'data-search': 'Robotik Berta Blau Dienstag', 'data-sort-default': '20', 'data-sort-title': 'Robotik', 'data-sort-category': 'Technik', 'data-sort-grade': '8', 'data-sort-leader': 'Berta Blau', 'data-sort-weekday': '2'}),
  makeAdminItem('admin-kunst', {'data-only-grade-7': '1', 'data-allowed-grades': '7', 'data-category': 'Kunst', 'data-weekdays': '1', 'data-leader': 'Änne Adler', 'data-search': 'Kunst Änne Adler Montag', 'data-sort-default': '10', 'data-sort-title': 'Kunst', 'data-sort-category': 'Kunst', 'data-sort-grade': '7', 'data-sort-leader': 'Änne Adler', 'data-sort-weekday': '1'}),
  makeAdminItem('admin-theater', {'data-only-grade-7': '0', 'data-allowed-grades': '', 'data-category': 'Bühne', 'data-weekdays': '3', 'data-leader': 'Berta Blau', 'data-search': 'Theater Berta Blau Mittwoch', 'data-sort-default': '30', 'data-sort-title': 'Theater', 'data-sort-category': 'Bühne', 'data-sort-grade': '0', 'data-sort-leader': 'Berta Blau', 'data-sort-weekday': '3'})
];
const adminDetails = new Map(adminItems.map((item) => [item.id, makeAdminDetails(item.id + '-details')]));
const adminContainer = {
  children: adminItems.flatMap((item) => [item, adminDetails.get(item.id)]),
  appendChild(item) {
    this.children = this.children.filter((child) => child !== item);
    this.children.push(item);
  }
};
const adminScope = {
  querySelector(selector) {
    const detailsMatch = selector.match(/^\[data-flz-ui-editable-details-for="(.+)"\]$/);
    if (detailsMatch) return adminDetails.get(detailsMatch[1]) || null;
    return {
      '[data-flz-ags-admin-search]': adminListControls.search,
      '[data-flz-ags-admin-category]': adminListControls.category,
      '[data-flz-ags-admin-grade]': adminListControls.grade,
      '[data-flz-ags-admin-leader]': adminListControls.leader,
      '[data-flz-ags-admin-weekday]': adminListControls.weekday,
      '[data-flz-ags-admin-items]': adminContainer,
      '[data-flz-ags-admin-results-status]': adminStatus,
      '[data-flz-ags-admin-no-results]': adminNoResults
    }[selector] || null;
  },
  querySelectorAll(selector) {
    if (selector === '[data-flz-ags-admin-item]') return adminItems;
    if (selector === '[data-flz-ags-admin-search]') return [adminListControls.search];
    if (selector === '[data-flz-ags-admin-category], [data-flz-ags-admin-grade], [data-flz-ags-admin-leader], [data-flz-ags-admin-weekday]') {
      return [adminListControls.category, adminListControls.grade, adminListControls.leader, adminListControls.weekday];
    }
    if (selector === '[data-flz-ags-admin-sort-button]') return adminSortButtons;
    return [];
  }
};
const adminHandlers = new Map();
const adminDocument = {
  querySelectorAll(selector) { return selector === '[data-flz-ags-admin-list]' ? [adminScope] : []; }
};
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
assert.deepEqual(adminContainer.children.filter((item) => adminItems.includes(item)), [adminItems[1], adminItems[0], adminItems[2]]);
assert.equal(adminStatus.textContent, '3 AGs angezeigt.');

adminListControls.category.value = 'Technik';
adminListControls.category.listeners.change();
assert.equal(adminItems[0].hidden, false);
assert.equal(adminItems[1].hidden, true);
adminListControls.category.value = '';
adminSortButtons[1].listeners.click();
assert.deepEqual(adminContainer.children.filter((item) => adminItems.includes(item)), [adminItems[2], adminItems[1], adminItems[0]]);

adminDetails.get('admin-robotik').hidden = false;
adminListControls.grade.value = '8';
adminListControls.weekday.value = '2';
adminListControls.leader.value = 'Berta Blau';
adminListControls.search.value = 'robotik';
adminListControls.search.listeners.input();
assert.equal(adminItems[0].hidden, false);
assert.equal(adminItems[1].hidden, true);
assert.equal(adminItems[2].hidden, true);
assert.equal(adminDetails.get('admin-robotik').hidden, false);
assert.equal(adminStatus.textContent, '1 AG angezeigt.');

adminListControls.search.value = 'fehlt';
adminListControls.search.listeners.input();
assert.equal(adminNoResults.hidden, false);
assert.equal(adminDetails.get('admin-robotik').hidden, true);
assert.equal(adminDetails.get('admin-robotik').getAttribute('data-flz-ags-filter-hidden'), 'true');

adminListControls.grade.value = '';
adminListControls.weekday.value = '';
adminListControls.leader.value = '';
adminListControls.search.value = '';
adminSortButtons[4].listeners.click();
assert.equal(adminDetails.get('admin-robotik').hidden, false);
assert.deepEqual(adminContainer.children.filter((item) => adminItems.includes(item)), [adminItems[1], adminItems[0], adminItems[2]]);
assert.equal(adminSortButtons[4].header.attributes['aria-sort'], 'ascending');
assert.equal(adminSortButtons[4].attributes['aria-pressed'], 'true');

adminSortButtons[4].listeners.click();
assert.deepEqual(adminContainer.children.filter((item) => adminItems.includes(item)), [adminItems[2], adminItems[0], adminItems[1]]);
assert.equal(adminSortButtons[4].header.attributes['aria-sort'], 'descending');

adminSortButtons[5].listeners.click();
assert.deepEqual(adminContainer.children.filter((item) => adminItems.includes(item)), [adminItems[2], adminItems[0], adminItems[1]]);
assert.equal(adminSortButtons[5].header.attributes['aria-sort'], 'none');

const imageInput = new Wrapper({value: 'vorher'});
const imageField = new Wrapper({findResults: {'[data-flz-ags-image-input]': imageInput}});
const clearButton = new Wrapper({closestResult: imageField});
let prevented = false;
adminHandlers.get('click:[data-flz-ags-clear-image]').call(clearButton, {preventDefault() { prevented = true; }});
assert.equal(prevented, true);
assert.equal(imageInput.subject.value, '');
assert.equal(imageInput.subject.triggered, true);
