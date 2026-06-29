import { describe, it, expect } from 'vitest';
import { parseDOB, formatDOB, ageFromDOB, toISODOB, resolveBornValue } from '@/utils/dob.js';

describe('parseDOB', () => {
  it('parses ISO YYYY-MM-DD in local time (no off-by-one)', () => {
    const d = parseDOB('2026-06-28');
    expect(d.getFullYear()).toBe(2026);
    expect(d.getMonth()).toBe(5); // June (0-indexed)
    expect(d.getDate()).toBe(28);
  });

  it('treats ambiguous slash dates as MM/DD/YYYY', () => {
    const d = parseDOB('07/04/2026');
    expect(d.getMonth()).toBe(6); // July
    expect(d.getDate()).toBe(4);
  });

  it('detects unambiguous DD/MM/YYYY when day > 12', () => {
    const d = parseDOB('25/12/2026');
    expect(d.getMonth()).toBe(11); // December
    expect(d.getDate()).toBe(25);
  });

  it('returns null for empty or unparseable values', () => {
    expect(parseDOB('')).toBeNull();
    expect(parseDOB(null)).toBeNull();
    expect(parseDOB('not a date')).toBeNull();
  });
});

describe('formatDOB / toISODOB', () => {
  it('formats to a short readable date', () => {
    expect(formatDOB('2026-06-28')).toBe('Jun 28, 2026');
  });
  it('normalizes any input to canonical YYYY-MM-DD', () => {
    expect(toISODOB('06/28/2026')).toBe('2026-06-28');
    expect(toISODOB('')).toBe('');
  });
});

describe('ageFromDOB', () => {
  it('computes whole-years age (exact on a birthday)', () => {
    const now = new Date();
    const dob = new Date(now.getFullYear() - 16, now.getMonth(), now.getDate());
    expect(ageFromDOB(dob)).toBe(16);
  });
  it('returns null when DOB is unknown', () => {
    expect(ageFromDOB(null)).toBeNull();
  });
});

describe('resolveBornValue', () => {
  it('pulls DOB from any known field/shape', () => {
    expect(resolveBornValue({ profile: { born_date: '2000-01-01' } })).toBe('2000-01-01');
    expect(resolveBornValue({ born: { date: '1999-05-05' } })).toBe('1999-05-05');
    expect(resolveBornValue({ dob: '2001-09-09' })).toBe('2001-09-09');
    expect(resolveBornValue({})).toBeNull();
  });
});
