#!/usr/bin/env python3
"""
Example script runner service for eLabFTW interactive bodies.

This is a small standalone HTTP service that receives script execution
requests relayed by eLabFTW (see the "Interactive experiment bodies"
documentation page) and runs the requested script.

Security notes:
- It only serves the loopback interface by default (127.0.0.1), so it is
  not reachable from the network. Do not bind it to 0.0.0.0.
- Only scripts placed in the scripts/ directory next to this file are
  accepted, as plain filenames. No path traversal.
- Take the time to think about which scripts you put there: anything
  that modifies files, sends requests or exposes data should validate
  its arguments carefully.

Usage:
    python3 script-runner.py [port]

The runner listens on 127.0.0.1:<port> (default 8765) and expects POST
requests with a JSON body: {"name": "script-name", "args": {...}}.
It replies with a JSON body: {"output": "..."}.

The eLabFTW side must have its script_runner_url config set to
http://127.0.0.1:8765 for this to work.

Requires Python 3.7+ (standard library only).
"""

import json
import re
import subprocess
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

# only allow plain filenames, no paths
NAME_REGEX = re.compile(r"^[a-zA-Z0-9][a-zA-Z0-9_\-.]{0,127}$")
# where the runnable scripts live
SCRIPTS_DIR = Path(__file__).resolve().parent / "scripts"
# requests larger than this are rejected
MAX_REQUEST_SIZE = 10000
RUN_TIMEOUT = 90


class ScriptRunnerHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != "/":
            self.send_json(404, {"error": "not found"})
            return
        length = int(self.headers.get("Content-Length", 0))
        if length <= 0 or length > MAX_REQUEST_SIZE:
            self.send_json(400, {"error": "invalid request size"})
            return
        try:
            payload = json.loads(self.rfile.read(length).decode("utf-8"))
        except (ValueError, UnicodeDecodeError):
            self.send_json(400, {"error": "invalid json"})
            return
        name = payload.get("name", "")
        args = payload.get("args", {})
        if not isinstance(name, str) or not NAME_REGEX.match(name):
            self.send_json(400, {"error": "invalid script name"})
            return
        if not isinstance(args, dict):
            self.send_json(400, {"error": "invalid args"})
            return
        script = SCRIPTS_DIR / name
        # belt and suspenders: make sure we stay in SCRIPTS_DIR
        try:
            script.resolve().relative_to(SCRIPTS_DIR.resolve())
        except ValueError:
            self.send_json(400, {"error": "invalid script name"})
            return
        if not script.is_file():
            self.send_json(404, {"error": "unknown script"})
            return
        try:
            result = subprocess.run(
                (sys.executable, str(script)),
                input=json.dumps(args),
                capture_output=True,
                text=True,
                timeout=RUN_TIMEOUT,
            )
        except subprocess.TimeoutExpired:
            self.send_json(500, {"error": "script timed out"})
            return
        output = result.stdout
        if result.returncode != 0:
            output += result.stderr
        self.send_json(200, {"output": output})

    def send_json(self, code, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, fmt, *args):
        sys.stderr.write("[script-runner] %s\n" % (fmt % args))


def main():
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8765
    SCRIPTS_DIR.mkdir(exist_ok=True)
    server = ThreadingHTTPServer(("127.0.0.1", port), ScriptRunnerHandler)
    print(f"script runner listening on 127.0.0.1:{port}, scripts in {SCRIPTS_DIR}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
