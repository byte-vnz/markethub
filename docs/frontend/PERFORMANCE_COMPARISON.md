# Frontend performance comparison

Measurements use headless Chrome 152.0.7977.83 and the same guarded fictional SQLite preview. These are resource counts and request-size observations from localhost. Field Core Web Vitals were outside this sample.

The local Chart.js artifact is 206,670 bytes. At 375 CSS px, Chrome Resource Timing reported a Chart.js entry on Dashboard, Settings and Tenant Management. The entry recorded 206,670 decoded bytes and 206,970 transfer bytes on each sampled page. The full Help reader uses a separate layout and had no Chart.js request. Use a consistent cache policy, browser and fixture for the paired comparison. Collect request count and decoded bytes before first interaction. Retain all Dashboard chart rendering and text data.

Two cache-disabled visits to the full Help manual at 375 CSS px and scroll position zero initiated 64 unique image URLs each. All 83 rendered image elements were below the first viewport at sampling time and none carried both width and height attributes. This confirms the design's lazy-loading trigger. These are request starts sampled about 700 ms after the document became available. Completed bytes depend on how many requests finished by that time. Full load, image decode shift, LCP, INP and CLS were outside this measurement.

| Check | Before | After |
| --- | --- | --- |
| Chart.js on Settings and Tenant Management | One request each at 375 CSS px, 206,670 decoded bytes | Zero requests, while Dashboard keeps the deferred library |
| Full Help manual image URLs started before scroll | 64 in both cold runs with 83 rendered figures | 1 in both cold runs with 85 rendered figures, including two new manual captures |
| Market lookups for 20 fictional legacy rates | 20 queries | 1 eager-loaded query |

Intrinsic image sizing was deferred because the experiment did not isolate a decode-related layout shift. Larger map, candidate lists and field LCP/INP/CLS remain future measurement work. Raw image samples are in `../../.qa/frontend-modernization/baseline-browser-probe.json` and `../../.qa/frontend-modernization/after-browser-probe.json`.
