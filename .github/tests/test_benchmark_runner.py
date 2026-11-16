"""Exercise the real benchmark runner without PHP, ARM hardware, or libraries."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class BenchmarkRunnerTest(unittest.TestCase):
    def run_runner(self, fail_at=0, model=True):
        with tempfile.TemporaryDirectory(prefix='benchmark runner ') as directory:
            root = Path(directory)
            log = root / 'calls'
            stubs = {
                'php': '''#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CALL_LOG"
count=$(wc -l < "$CALL_LOG")
if [ "$count" -eq "$FAIL_AT" ]; then
    echo 'benchmark failed' >&2
    exit 23
fi
printf '%s 12345 cs=1\\n' "$4"
''',
                'taskset': '#!/usr/bin/env bash\nshift 2\nexec "$@"\n',
                'lscpu': '#!/usr/bin/env bash\nprintf "%s\\n" "$CPU_INFO"\n',
            }
            for name, content in stubs.items():
                path = root / name
                path.write_text(content)
                path.chmod(0o755)
            env = dict(os.environ, PATH=str(root) + os.pathsep + os.environ['PATH'],
                       CALL_LOG=str(log), FAIL_AT=str(fail_at),
                       CPU_INFO='Model name: fixture' if model else 'Architecture: aarch64')
            result = subprocess.run(
                [str(ROOT / 'bench/run_neon.sh'), 'neon.so', 'scalar.so', '0', '1', '1'],
                env=env, capture_output=True, text=True,
            )
            return result, log.read_text().splitlines() if log.exists() else []

    def test_success_runs_both_comparisons(self):
        result, calls = self.run_runner()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(len(calls), 17)
        self.assertIn('ramsey_parse', result.stdout)
        self.assertIn('12345', result.stdout)

    def test_missing_optional_cpu_model_does_not_stop_benchmarks(self):
        result, calls = self.run_runner(model=False)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(len(calls), 17)

    def test_every_failed_benchmark_stops_the_runner(self):
        # Covers both awk pipelines and every direct call in the headline loop.
        for fail_at in range(1, 18):
            with self.subTest(fail_at=fail_at):
                result, calls = self.run_runner(fail_at)
                self.assertEqual(result.returncode, 23, result.stderr)
                self.assertEqual(len(calls), fail_at)
                self.assertIn('benchmark failed', result.stderr)


if __name__ == '__main__':
    unittest.main()
