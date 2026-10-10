import {describe,it,expect} from 'vitest';
import {skillFields,skillRates,matchesSkillSession} from '../../resources/js/features/planner/lib/skillResults';
describe('skill result presentation',()=>{
 it('does not invent rates for missing measurements or zero attempts',()=>{
  expect(skillRates({swings:0,contacts:0})).toEqual([]);
  expect(skillRates({swings:10})).toEqual([]);
  expect(skillRates({swings:10,contacts:0})).toEqual(['Contact / swings: 0% (0/10)']);
 });
 it('uses explicit denominators for hitting and pitching',()=>{
  expect(skillRates({swings:20,contacts:10,hard_contacts:5,pitches:20,strikes:12})).toEqual(['Contact / swings: 50% (10/20)','Hard contact / contacts: 50% (5/10)','Strikes / pitches: 60% (12/20)']);
 });
 it('limits session choices to the right discipline',()=>{
  expect(matchesSkillSession({type:'T',modes:'EV'},'hitting')).toBe(true);
  expect(matchesSkillSession({type:'P'},'hitting')).toBe(false);
  expect(matchesSkillSession({type:'P'},'pitching')).toBe(true);
  expect(skillFields('recovery')).toEqual([]);
 });
});
