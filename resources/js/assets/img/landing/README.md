# Public landing page captures

Supply these three real, approved FMTRX screenshots:

- `hitting.webp`: spray chart, exit velocity and hard-hit metrics.
- `bullpen.webp`: pitch-location heatmap, velocity, strike percentage and pitch counts.
- `weighted-balls.webp`: average/top/expected velocity curves by ball weight and throw counts.

Use anonymized demonstration test data. Exclude account details, invitation codes and private player information. Do not substitute generated dashboards or invented numbers. Capture at 1600 × 1000 pixels (or update the dimensions in `platformSections.js`). Optimize WebP while preserving readable labels; aim for 200–400 KB per image where practical.

`platformSections.js` automatically discovers these filenames at build time. Hitting and weighted balls are reused in the hero. Below-the-fold captures are lazy-loaded. Clicking a supplied capture opens its accessible full-screen dialog. Missing captures display an explicit preview placeholder with no synthetic statistics.

The approved reference URL returned HTTP 401 during implementation. Supply a publicly accessible reference or exported assets for final visual comparison.

## Supplied weighted-ball capture

`weighted-balls.webp` is a 990 × 574 crop extracted at 39 seconds from the user-supplied `weighted balls new screen.mov`. It shows the Team Average panel and original recorded metrics. The player selector and account navigation are excluded. Encoded as WebP quality 90; no metrics were altered. Hitting and bullpen source files are still pending.
