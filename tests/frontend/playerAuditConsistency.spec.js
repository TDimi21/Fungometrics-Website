import {it, expect} from 'vitest'
import {sessionParticipants} from '../../resources/js/utils/sessionParticipants.js'
import {ordinal} from '../../resources/js/utils/ordinal.js'
it('resolves nested bullpen players and deduplicates actual player IDs', () => {
  expect(sessionParticipants({lineup: [{id:'lineup', player: {id:'p1', name:{full:'Web Test'}}}], results:[{id:'pitch', pitcher_id:'p1'}]})).toEqual([{id:'p1', name:'Web Test'}])
})
it('does not count unrelated lineup IDs or the session owner as players', () => {
  expect(sessionParticipants({user_id:'coach', lineup:[{id:'lineup'}]})).toEqual([])
})
it.each([[31,'31st'],[63,'63rd'],[11,'11th'],[12,'12th'],[13,'13th'],[22,'22nd'],[null,'—']])('formats %s as %s', (value, expected) => expect(ordinal(value)).toBe(expected))
