import { spawn } from 'node:child_process';

export function runVerificationProcess(command, { cwd, env, timeout, grace = 5_000, onOutput = () => {} }) {
    return new Promise(resolve => {
        const child = spawn(command[0], command.slice(1), { cwd, env, detached: true, stdio: ['ignore', 'pipe', 'pipe'] });
        let output = '';
        let stoppedCode = null;
        let killTimer;
        let drainTimer;
        let finished = false;

        function signalGroup(signal) {
            if (!child.pid) return;
            try { process.kill(-child.pid, signal); } catch (error) { if (error.code !== 'ESRCH') throw error; }
        }

        function finish(code) {
            if (finished) return;
            finished = true;
            clearTimeout(timer);
            clearTimeout(killTimer);
            clearTimeout(drainTimer);
            process.off('SIGINT', interrupt);
            process.off('SIGTERM', terminate);
            const hiddenSkip = /(?:ℹ|#) (?:skipped|todo|cancelled) [1-9]/.test(output);
            resolve({ code: stoppedCode ?? (hiddenSkip ? 1 : (code ?? 1)), timedOut: stoppedCode === 124, output });
        }

        function stop(code) {
            if (stoppedCode !== null || finished) return;
            stoppedCode = code;
            // BrowserSuiteRunner owns a separate child session and needs this signal to clean it up.
            signalGroup('SIGTERM');
            killTimer = setTimeout(() => {
                signalGroup('SIGKILL');
                drainTimer = setTimeout(() => {
                    child.stdout.destroy();
                    child.stderr.destroy();
                    finish(code);
                }, 1_000);
            }, grace);
        }

        const interrupt = () => stop(130);
        const terminate = () => stop(143);
        process.on('SIGINT', interrupt);
        process.on('SIGTERM', terminate);
        const timer = setTimeout(() => stop(124), timeout);
        for (const stream of [child.stdout, child.stderr]) stream.on('data', chunk => { output += chunk; onOutput(chunk); });
        child.on('error', error => { output += error.message; });
        child.on('close', finish);
    });
}
