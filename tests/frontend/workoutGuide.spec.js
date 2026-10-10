import {describe, it, expect} from 'vitest';
import {readFileSync} from 'node:fs';
import {parse} from '@vue/compiler-sfc';
import {compile} from '@vue/compiler-dom';
import * as Vue from 'vue';

// Exercise the actual guide template with Vue's renderer, without a browser.
const template = parse(readFileSync('resources/js/components/workouts/WorkoutGuide.vue', 'utf8')).descriptor.template.content;
const render = new Function('Vue', compile(template, {mode: 'function', prefixIdentifiers: true}).code)(Vue);
function mount(step, included = []) {
  const events = [];
  const make = (type, text = '') => ({type, text, children: [], props: {}});
  const renderer = Vue.createRenderer({
    createElement: make, createText: text => make('text', text), createComment: text => make('comment', text),
    setText: (node, text) => node.text = text,
    setElementText: (node, text) => { node.text = text; node.children = []; },
    patchProp: (node, key, old, value) => node.props[key] = value,
    insert: (node, parent) => {parent.children.push(node); node.parent = parent;},
    remove: () => {}, parentNode: node => node.parent, nextSibling: () => null,
  });
  const buckets = [{type: 'throwing', title: 'Throwing', hint: 'Catch play'}, {type: 'recovery', title: 'Recovery', hint: 'Cool down'}];
  const root = make('root');
  renderer.createApp({render, setup: () => ({step, included, buckets, emit: (...args) => events.push(args)})}).mount(root);
  const text = node => node.text + node.children.map(text).join('');
  const nodes = node => [node, ...node.children.flatMap(nodes)];
  const button = label => nodes(root).find(n => n.type === 'button' && text(n).includes(label));
  return {events, button, text: text(root), buckets};
}
describe('guided workout sections', () => {
  it('lets a coach skip an unused section without adding it', () => {
    const view = mount(1);
    view.button('Skip section').props.onClick();
    expect(view.events).toEqual([['update:step', 2]]);
  });
  it('adds the selected bucket only on Use this section', () => {
    const view = mount(1);
    view.button('Use this section').props.onClick();
    expect(view.events).toEqual([['use', view.buckets[0]]]);
  });
  it('offers Next for included buckets, preserving them when navigating', () => {
    const view = mount(1, ['throwing']);
    expect(view.button('Use this section')).toBeUndefined();
    view.button('Next').props.onClick();
    expect(view.events).toEqual([['update:step', 2]]);
  });
  it('opens preview from details or the final review without changing steps', () => {
    for (const step of [0, 3]) {
      const view = mount(step);
      view.button('Preview workout').props.onClick();
      expect(view.events).toEqual([['preview']]);
      if (step === 3) expect(view.button('Next')).toBeUndefined();
    }
  });
});
