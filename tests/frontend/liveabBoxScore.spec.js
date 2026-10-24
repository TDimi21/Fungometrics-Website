import { it, expect } from "vitest";
import { liveabBoxScore } from "../../resources/js/utils/liveabBoxScore";
it("separates walks and home runs using legacy base codes and explicit outcomes", () => {
  const base = {
    batting: { batter_id: "b" },
    pitching: { pitcher_id: "p", miles_per_hour: 90 },
    is_strike: true,
  };
  const box = liveabBoxScore([
    { ...base, bases: 4, play_result: "WALK" },
    { ...base, bases: 5, play_result: "HR", runs_scored: 2, rbi: 2 },
    { ...base, bases: 7, play_result: "STRIKEOUT_SWINGING", outs_recorded: 1 },
  ]);
  expect(box.hitters[0]).toMatchObject({
    PA: 3,
    AB: 2,
    H: 1,
    homeRun: 1,
    BB: 1,
    SO: 1,
    RBI: 2,
    AVG: 0.5,
    SLG: 2,
  });
  expect(box.pitchers[0]).toMatchObject({
    IP: "0.1",
    H: 1,
    BB: 1,
    SO: 1,
    R: 2,
    pitches: 3,
  });
});
it("includes runner-only runs and outs without adding a pitch or RBI", () => {
  const before = {
      inning: 1,
      half: "top",
      outs: 2,
      scores: [{ runs: 0 }],
      pitcherIndex: { home: 0 },
    },
    after = { inning: 1, half: "bottom", outs: 0, scores: [{ runs: 1 }] };
  const box = liveabBoxScore(
    [{ batting: { batter_id: "b" }, pitching: { pitcher_id: "p" }, bases: 0 }],
    [
      {
        action: "wild_pitch",
        before_state: JSON.stringify(before),
        after_state: JSON.stringify(after),
      },
    ],
    { home: { pitchers: ["p"] } },
  );
  expect(box.pitchers[0]).toMatchObject({ pitches: 1, R: 1, IP: "0.1" });
  expect(box.hitters[0].RBI).toBe(0);
});
