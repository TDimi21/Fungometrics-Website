import {setupPayload} from '@/features/long-toss/sessionSetup'
import { it, expect, vi } from "vitest";
import { readFileSync } from "node:fs";
import { parse, compileScript } from "@vue/compiler-sfc";
import { computed, ref } from "vue";
import { cageDimensions } from "@/utils/cageScoring";
const source = compileScript(
  parse(
    readFileSync("resources/js/pages/training/CreatePlayerSession.vue", "utf8"),
  ).descriptor,
  { id: "player-create" },
)
  .content.replace(/^import[\s\S]*?from ['"][^'"]+['"];?\s*$/gm, "")
  .replace("export default", "return");
function screen(mode, query = {}) {
  const api = {
      axiosGet: vi.fn(async () => ({
        data: { data: [{ id: "team1", name: "My team" }] },
      })),
      axiosPost: vi.fn(async () => ({ data: { data: { id: "session1" } } })),
    },
    router = { push: vi.fn() };
  const mocks = { LongTossSetup: {}, setupPayload,
    onMounted: () => {},
    computed,
    ref,
    cageDimensions,
    Layout: {},
    useRoute: () => ({ params: { mode }, query }),
    useRouter: () => router,
    useUserStore: () => ({
      userData: { id: "self", type: "player", name: { full: "Player" } },
    }),
    useTrainingStore: () => ({ setDataTraining: vi.fn() }),
    useAxiosAuth: () => api,
  };
  const component = new Function(...Object.keys(mocks), source)(
    ...Object.values(mocks),
  );
  return { s: component.setup({}, { expose: () => {} }), api, router };
}
it.each([
  ["bullpen", "P", "/track/bullpen"],
  ["batting", "B", "/track/batting"],
  ["cage", "C", "/track/cage"],
  ["LT", "T", "/track/training-mode/LT"],
  ["EV", "T", "/track/training-mode/EV"],
  ["WB", "T", "/track/training-mode/WB"],
])(
  "creates a personal %s session and opens its scorer",
  async (mode, type, path) => {
    const { s, api, router } = screen(mode);
    await s.create();
    const payload = api.axiosPost.mock.calls[0][1];
    expect(payload).toMatchObject({ type, players: [{ id: "self", sort: 0 }] });
    expect(payload).not.toHaveProperty("team");
    if (type === "T") expect(payload.modes).toBe(mode);
    if (type === "C") expect(cageDimensions(payload.cage)).toBeTruthy();
    expect(router.push).toHaveBeenCalledWith({
      path,
      query: { session: "session1" },
    });
  },
);
it("blocks invalid cage dimensions and unknown modes", async () => {
  const { s, api } = screen("cage");
  s.cage.value.width.inch = 12;
  await s.create();
  expect(api.axiosPost).not.toHaveBeenCalled();
  const other = screen("live");
  await other.s.create();
  expect(other.api.axiosPost).not.toHaveBeenCalled();
});
it("prevents duplicate creates during an in-flight or uncertain request", async () => {
  const { s, api } = screen("batting");
  api.axiosPost.mockRejectedValue(Error("lost"));
  await Promise.all([s.create(), s.create()]);
  await s.create();
  expect(api.axiosPost).toHaveBeenCalledTimes(1);
  expect(s.uncertain.value).toBe(true);
});

it("shares only with a loaded connected team while scoring self", async () => {
  const { s, api } = screen("LT");
  await s.loadTeams();
  s.sharingTeam.value = ["team1"];
  await s.create();
  expect(api.axiosPost.mock.calls[0][1]).toMatchObject({
    share_with_team_ids: ["team1"],
    players: [{ id: "self", sort: 0 }],
  });
  const other = screen("LT");
  other.s.sharingTeam.value = ["foreign"];
  await other.s.create();
  expect(other.api.axiosPost).not.toHaveBeenCalled();
});

it("shares one personal session with multiple connected teams", async () => {
  const { s, api } = screen("WB");
  s.connectedTeams.value = [{ id: "a" }, { id: "b" }];
  s.sharingTeam.value = ["a", "b"];
  await s.create();
  expect(api.axiosPost).toHaveBeenCalledTimes(1);
  expect(api.axiosPost.mock.calls[0][1]).toMatchObject({
    share_with_team_ids: ["a", "b"],
    players: [{ id: "self", sort: 0 }],
  });
});

it("links assigned workouts only after an explicit sharing choice", async () => {
  const {s,api,router}=screen('bullpen',{planner_plan:'plan1',planner_item:'item1',planner_team:'team1'});
  await s.loadTeams();
  expect(s.sharingTeam.value).toEqual([]);
  s.sharingTeam.value=['team1'];
  await s.create();
  expect(api.axiosPost.mock.calls[0][1]).toMatchObject({planner_plan_id:'plan1',planner_item_id:'item1',share_with_team_ids:['team1'],players:[{id:'self',sort:0}]});
  expect(router.push).toHaveBeenCalledWith({path:'/track/bullpen',query:{session:'session1'}});
});

it('requires complete guided details before creating personal Long Toss',async()=>{
 const {s,api}=screen('LT');s.longTossMode.value='guided';await s.create();
 expect(api.axiosPost).not.toHaveBeenCalled();expect(s.uncertain.value).toBe(false);
});
