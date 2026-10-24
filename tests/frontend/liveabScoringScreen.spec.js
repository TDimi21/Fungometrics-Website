import { describe, it, expect, vi } from "vitest";
import { readFileSync } from "node:fs";
import { parse, compileScript } from "@vue/compiler-sfc";
import * as Vue from "vue";
import * as scoring from "@/utils/liveabWebScoring";
import { getPitchInfoFromCoordinate } from "@/utils/bullpenCoordinates";
import { getBattingFieldInfoFromCoordinate } from "@/utils/battingCoordinates";
const source = compileScript(
  parse(readFileSync("resources/js/pages/training/LiveABScoring.vue", "utf8"))
    .descriptor,
  { id: "liveab-test" },
).content;
const script = source
  .replace(/^import[\s\S]*?from ['"][^'"]+['"];?\s*$/gm, "")
  .replace("export default", "return");
async function screen({
  lost = false,
  completed = false,
  half = "top",
  strikes = 0,
  outs = 0,
} = {}) {
  const config = {
    mode: "game",
    name: "Game",
    innings: 7,
    unlimited: false,
    continuous: false,
    away: { team_id: "away", hitters: ["a"], pitchers: ["ap"] },
    home: { team_id: "home", hitters: ["h"], pitchers: ["hp"] },
  };
  let data = {
    id: "s",
    config,
    is_completed: completed,
    teams: [],
    players: {
      batters: [
        { id: "a", name: { full: "Away hitter" } },
        { id: "h", name: { full: "Home hitter" } },
      ],
      pitchers: [{ id: "ap" }, { id: "hp" }],
    },
    rows: [],
    can_undo: false,
    state: {
      inning: 1,
      half,
      outs,
      balls: 0,
      strikes,
      turn: 1,
      pitches: 0,
      runners: [null, null, null],
      scores: [],
      hitterIndex: { away: 0, home: 0 },
      pitcherIndex: { away: 0, home: 0 },
      finished: false,
      revision: 0,
    },
  };
  let attempts = 0,
    leave;
  const api = {
    axiosGet: vi.fn(async () => ({ data: { data } })),
    axiosPut: vi.fn(async () => ({})),
    axiosPost: vi.fn(async (path, body) => {
      if (lost && attempts++ === 0) throw Error("lost");
      data = {
        ...data,
        state: { ...body.state, revision: body.revision + 1 },
        rows: [{ id: "r", batting: body.batting, pitching: body.pitching }],
        can_undo: true,
      };
      return { data: { data } };
    }),
  };
  const mocks = {
    ...scoring,
    computed: Vue.computed,
    ref: Vue.ref,
    onMounted: Vue.onMounted,
    getPitchInfoFromCoordinate,
    getBattingFieldInfoFromCoordinate,
    Layout: {},
    BattingScoringField: {},
    LivePracticeStats: {},
    LiveABBoxScore: {},
    useRoute: () => ({ query: { session: "s" } }),
    useRouter: () => ({ push: vi.fn() }),
    useTrainingStore: () => ({ trainingActive: {} }),
    useAxiosAuth: () => api,
    onBeforeRouteLeave: (fn) => {
      leave = fn;
    },
    crypto: { randomUUID: () => "same-request" },
  };
  const component = new Function(...Object.keys(mocks), script)(
    ...Object.values(mocks),
  );
  let s;
  const renderer = Vue.createRenderer({
    createComment: () => ({}),
    remove: () => {},
    insert: () => {},
    parentNode: () => null,
    nextSibling: () => null,
  });
  const app = renderer.createApp({
    setup() {
      s = component.setup({}, { expose: () => {} });
      return () => null;
    },
  });
  app.mount({});
  await new Promise((r) => setTimeout(r, 0));
  s.location.value = 1830;
  s.velocity.value = "90";
  return { s, api, app, leave: () => leave() };
}
describe("LiveAB web scorer lifecycle", () => {
  it.each(["top", "bottom"])(
    "saves the correct pitcher and hitter teams in %s",
    async (half) => {
      const { s, api, app } = await screen({ half });
      await s.save();
      expect(api.axiosPost).toHaveBeenCalledTimes(1);
      expect(api.axiosPost.mock.calls[0][1]).toMatchObject({
        pitching: {
          team_id: half === "top" ? "home" : "away",
          pitcher_id: half === "top" ? "hp" : "ap",
        },
        batting: {
          team_id: half === "top" ? "away" : "home",
          batter_id: half === "top" ? "a" : "h",
        },
        state: { pitches: 1 },
      });
      app.unmount();
    },
  );
  it("locks uncertain submissions and retries the identical request", async () => {
    const { s, api, app, leave } = await screen({ lost: true });
    await s.save();
    await s.save();
    expect(api.axiosPost).toHaveBeenCalledTimes(1);
    expect(leave()).toBe(false);
    const pending = s.pending.value;
    await s.requestAction(pending.kind, pending.payload);
    expect(api.axiosPost.mock.calls[1][1]).toEqual(
      api.axiosPost.mock.calls[0][1],
    );
    expect(s.pending.value).toBeNull();
    expect(s.rows.value).toHaveLength(1);
    app.unmount();
  });
  it("requires ball-in-play review and moves sides after the third out", async () => {
    const { s, api, app } = await screen({ strikes: 2, outs: 2 });
    s.trajectory.value = "SM";
    s.prepare();
    expect(s.review.value).toBe(true);
    expect(api.axiosPost).not.toHaveBeenCalled();
    await s.save();
    expect(s.state.value.half).toBe("bottom");
    expect(s.hitter.value).toBe("h");
    expect(s.pitcher.value).toBe("ap");
    app.unmount();
  });
  it("does not save completed sessions", async () => {
    const { s, api, app } = await screen({ completed: true });
    await s.save();
    expect(api.axiosPost).not.toHaveBeenCalled();
    app.unmount();
  });
});
