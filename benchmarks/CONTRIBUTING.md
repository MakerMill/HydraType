# Benchmark development

Benchmark scenarios should keep the operation under test visible in their own
script. Shared support exists only for work that must behave consistently across
the suite:

- `BenchmarkRunner` performs warm-up, randomized interleaved sampling, timing,
  normalization, and post-measurement verification.
- `BenchmarkCase` separates the timed operation from preparation, warm-up, and
  verification callbacks.
- `Statistics` provides the common median and nearest-rank percentile
  definitions.
- `Environment` reports the PHP, JIT, and Xdebug context.
- `Options` normalizes repeated command-line option shapes.

Do not move the loop or operation being compared into support code merely to
reduce line count. A reader should be able to inspect a scenario and see exactly
what runs inside its timed closure. Preparation and correctness checks belong
outside that interval.

When changing shared support, run the full project checks and representative
small runs of every benchmark that uses the changed helper. Performance results
must still be recorded with normal sample and iteration counts; reduced runs
only verify execution and output shape.
