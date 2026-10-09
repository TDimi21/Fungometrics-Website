import { describe, it, expect } from "vitest";
import {
  copyEntries,
  overlapWarnings,
} from "@/features/workouts/programSchedule";
describe("workout schedule snapshots", () => {
  const entries = [
    {
      id: "a",
      day_offset: 0,
      player_ids: ["p"],
      snapshot: {
        intensity_label: "High",
        sections: [
          {
            exercises: [
              {
                prescription_text:
                  "3–4 throws each with 5, 6, 7, 5, 4, and 3 oz balls",
              },
            ],
          },
        ],
      },
    },
    {
      id: "b",
      day_offset: 3,
      player_ids: ["p2"],
      snapshot: { intensity_label: "Recovery" },
    },
  ];
  it("copies a week with separate IDs, athlete lists and prescriptions", () => {
    let id = 0;
    const copy = copyEntries(entries, 0, 7, 7, 28, () => String(++id));
    expect(copy.map((e) => e.day_offset)).toEqual([7, 10]);
    copy[0].snapshot.sections[0].exercises[0].prescription_text =
      "Coach adjustment";
    copy[0].player_ids.push("p3");
    expect(entries[0].player_ids).toEqual(["p"]);
    expect(
      entries[0].snapshot.sections[0].exercises[0].prescription_text
    ).toContain("5, 6, 7, 5, 4, and 3 oz");
    expect(copy[0].id).not.toBe(entries[0].id);
  });
  it("does not create dates outside the program or accept negative/fractional copy ranges", () => {
    expect(
      copyEntries(entries, 0, 27, 7, 28, () => "new").map((e) => e.day_offset)
    ).toEqual([27]);
    expect(copyEntries(entries, 0, -1, 7, 28, () => "new")).toEqual([]);
    expect(copyEntries(entries, 0, 1.5, 7, 28, () => "new")).toEqual([]);
  });
  it("warns for the same athlete without changing workload or creating schedules", () => {
    const schedule = [...entries, { ...entries[0], id: "c", day_offset: 1 }];
    const before = JSON.stringify(schedule);
    expect(overlapWarnings(schedule)).toHaveLength(1);
    expect(JSON.stringify(schedule)).toBe(before);
    expect(overlapWarnings(entries)).toEqual([]);
  });
});
