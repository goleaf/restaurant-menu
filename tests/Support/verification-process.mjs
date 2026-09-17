import { spawn } from 'node:child_process';

export function runVerificationProcess(command, { cwd, env, timeout, grace = 5_000, onOutput = () => {} }) {
    return new Promise(resolve => {
        const child = spawn(command[0], command.slice(1), { cwd, env, detached: true, stdio: ['ignore', 'pipe', 'pipe'] });
        let output = '';
        let stoppedCode = null;
        let killTimer;
        let drainTimer;
        let finished = false;
        let cleanupPromise;
        let cleanupFailed = false;

        function signalGroup(signal) {
            if (!child.pid) return;
            try { process.kill(-child.pid, signal); } catch (error) { if (error.code !== 'ESRCH') throw error; }
        }

        function groupRunning() {
            if (!child.pid) return false;
            try { process.kill(-child.pid, 0); return true; } catch (error) {
                if (error.code === 'ESRCH') return false;
                // A denied probe does not establish absence; keep the bounded cleanup checks.
                if (error.code === 'EPERM') return true;
                throw error;
            }
        }

        async function cleanGroup() {
            for (const [signal, duration] of [['SIGTERM', grace], ['SIGKILL', 1_000]]) {
                if (!groupRunning()) return;
                signalGroup(signal);
                const deadline = Date.now()+duration;
                while (groupRunning() && Date.now() < deadline) await new Promise(done => setTimeout(done, 20));
            }
            if (groupRunning()) throw new Error('Verification could not stop its owned process group.');
        }

        function cleanup() {
            cleanupPromise ??= cleanGroup().catch(error => { cleanupFailed = true; output += error.message; });
            return cleanupPromise;
        }

        async function finish(code) {
            if (finished) return;
            finished = true;
            clearTimeout(timer);
            clearTimeout(killTimer);
            clearTimeout(drainTimer);
            await cleanup();
            process.off('SIGINT', interrupt);
            process.off('SIGTERM', terminate);
            const hiddenSkip = /(?:ℹ|#) (?:skipped|todo|cancelled) [1-9]/.test(output);
            resolve({ code: stoppedCode ?? (hiddenSkip || cleanupFailed ? 1 : (code ?? 1)), timedOut: stoppedCode === 124, output });
        }

        function stop(code) {
            if (stoppedCode !== null) return;
            stoppedCode = code;
            if (finished) { signalGroup('SIGKILL'); return; }
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
        child.on('exit', code => {
            clearTimeout(timer);
            clearTimeout(killTimer);
            clearTimeout(drainTimer);
            return cleanup().then(() => {
                if (finished) return;
                drainTimer = setTimeout(() => {
                    output += '\nVerification output pipes remained open after process cleanup.';
                    child.stdout.destroy();
                    child.stderr.destroy();
                    finish(code === 0 ? 1 : code);
                }, 1_000);
            });
        });
        child.on('close', finish);
    });
}
