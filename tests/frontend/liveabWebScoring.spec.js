import { describe, it, expect } from "vitest";
import {
  countAfter,
  defaultMovements,
  scorePitch,
  advanceHalf,
  totals,
  resolveMovements,
  adjustRunners,
} from "../../resources/js/utils/liveabWebScoring";
const config = {
  mode: "game",
  innings: 7,
  unlimited: false,
  continuous: false,
  away: { hitters: ["a", "b"] },
  home: { hitters: ["c", "d"] },
};
const initial = () => ({
  inning: 1,
  half: "top",
  outs: 0,
  balls: 0,
  strikes: 0,
  turn: 1,
  pitches: 0,
  runners: [null, null, null],
  scores: [],
  hitterIndex: { away: 0, home: 0 },
  pitcherIndex: { away: 0, home: 0 },
  finished: false,
});
describe("LiveAB game scoring", () => {
  it("holds two strikes on a foul and awards a walk on ball four", () => {
    expect(countAfter({ ...initial(), strikes: 2 }, "S", "F").strikes).toBe(2);
    expect(countAfter({ ...initial(), balls: 3 }, "B", "TK").result).toBe(
      "WALK",
    );
  });
  it("does not advance unforced runners on a walk", () => {
    const state = { ...initial(), runners: [null, "runner2", "runner3"] };
    expect(defaultMovements(state, "batter", "WALK").map((r) => r.to)).toEqual([
      1, 2, 3,
    ]);
  });
  it("forces a bases-loaded walk and advances the hitter", () => {
    const state = { ...initial(), balls: 3, runners: ["r1", "r2", "r3"] };
    const scored = scorePitch(state, config, {
      zone: "B",
      trajectory: "TK",
      movements: defaultMovements(state, "a", "WALK"),
    });
    expect(scored.play.bases).toBe(4);
    expect(scored.state.runners).toEqual(["a", "r1", "r2"]);
    expect(totals(scored.state).away).toBe(1);
    expect(scored.state.hitterIndex.away).toBe(1);
    expect(scored.state.balls).toBe(0);
  });
  it("switches sides after the third strikeout", () => {
    const state = { ...initial(), outs: 2, strikes: 2 };
    const scored = scorePitch(state, config, {
      zone: "S",
      trajectory: "SM",
      movements: defaultMovements(state, "a", "STRIKEOUT_SWINGING"),
    });
    expect(scored.state.half).toBe("bottom");
    expect(scored.state.outs).toBe(0);
    expect(scored.play.outs_recorded).toBe(1);
    expect(scored.play.bases).toBe(7);
  });
  it("counts a grand slam and preserves its runs when changing innings", () => {
    const state = { ...initial(), runners: ["r1", "r2", "r3"] };
    const hit = scorePitch(state, config, {
      zone: "S",
      trajectory: "FB",
      result: "HR",
      movements: defaultMovements(state, "a", "HR"),
    });
    expect(hit.play.rbi).toBe(4);
    expect(hit.play.bases).toBe(5);
    expect(totals(advanceHalf(hit.state, config)).away).toBe(4);
  });
  it("cancels runs on an inning-ending force and rejects conflicting bases", () => {
    expect(
      resolveMovements(
        [
          { id: "a", to: 0 },
          { id: "r", to: 4 },
        ],
        2,
        "force",
      ).runs,
    ).toBe(0);
    expect(() =>
      resolveMovements([
        { id: "a", to: 1 },
        { id: "b", to: 1 },
      ]),
    ).toThrow("Two runners");
  });
  it("finishes a walkoff and allows tied extra innings", () => {
    const state = { ...initial(), inning: 7, half: "bottom" };
    const hit = scorePitch(state, config, {
      zone: "S",
      trajectory: "FB",
      result: "HR",
      movements: defaultMovements(state, "c", "HR"),
    });
    expect(hit.state.finished).toBe(true);
    expect(advanceHalf(state, config).inning).toBe(8);
  });
  it("keeps the lineup slot when a runner makes the third out before a completed PA", () => {
    const state = {
      ...initial(),
      outs: 2,
      balls: 2,
      runners: ["r", null, null],
    };
    const next = adjustRunners(
      state,
      config,
      [{ id: "r", from: 1, to: 0 }],
      "caught_stealing",
    );
    expect(next.half).toBe("bottom");
    expect(next.hitterIndex.away).toBe(0);
    expect(next.balls).toBe(0);
  });
  it("does not automatically switch continuous innings", () => {
    const state = { ...initial(), outs: 2, strikes: 2 };
    const next = scorePitch(
      state,
      { ...config, continuous: true },
      {
        zone: "S",
        trajectory: "SM",
        movements: defaultMovements(state, "a", "STRIKEOUT_SWINGING"),
      },
    ).state;
    expect(next.half).toBe("top");
    expect(next.outs).toBe(0);
    const runnerOut=adjustRunners({...state,runners:['r',null,null]}, {...config,continuous:true}, [{id:'r',from:1,to:0}], 'caught_stealing');
    expect(runnerOut.half).toBe('top');

  });
});
