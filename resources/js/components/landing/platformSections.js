// Only approved, anonymized platform captures belong here. Missing assets stay
// explicit rather than substituting generated charts or invented performance data.
const captures = import.meta.glob('../../assets/img/landing/*.webp', { eager: true, query: '?url', import: 'default' });
const capture = (name) => captures[`../../assets/img/landing/${name}.webp`] || null;
export const platformSections = [
  {
    id: 'hitting', label: 'Hitting', title: 'Every swing tells a story.',
    description: 'Connect where the ball goes with how it leaves the bat. Review contact location, exit velocity, and batted-ball types to give the next round a purpose.',
    features: ['See contact patterns on the spray chart', 'Review exit velocity and hard-hit metrics', 'Break down ground balls, line drives, and fly balls'],
    src: capture('hitting'), width: 1600, height: 1000,
    alt: 'FMTRX hitting report with a spray chart, exit velocity, and hard-hit metrics. Demonstration test data.',
  },
  {
    id: 'bullpen', label: 'Bullpen', title: 'Make command visible.',
    description: 'See the locations behind the numbers. Review a bullpen by pitch type, then use velocity, strike percentage, and pitch counts to understand command and workload.',
    features: ['Explore pitch-location heatmaps', 'Filter the report by pitch type', 'Review velocity, strike percentage, and pitch counts'],
    src: capture('bullpen'), width: 1600, height: 1000,
    alt: 'FMTRX bullpen report with a pitch-location heatmap, velocity, strike percentage, and pitch counts. Demonstration test data.',
  },
  {
    id: 'weighted-balls', label: 'Weighted balls', title: 'See the shape of every throw.',
    description: 'Compare average and top velocity across ball weights, alongside expected reference curves. Keep throw counts in view as you review the session with your coach.',
    features: ['Compare average and top velocity by ball weight', 'View expected curves as a reference, not a prescribed target', 'Keep track of recorded throws at each weight'],
    src: capture('weighted-balls'), width: 990, height: 574,
    alt: 'FMTRX weighted-ball report with average, top, and expected velocity curves across ball weights and throw counts. Demonstration test data.',
  },
];
