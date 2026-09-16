import assert from 'node:assert/strict';
import test from 'node:test';
import { runVerificationProcess } from './Support/verification-process.mjs';

const execute = (source, options = {}) => runVerificationProcess([process.execPath, '-e', source], {
    env: process.env, timeout: 5_000, grace: 1_000, ...options,
});

test('verification preserves failures and rejects an otherwise successful skipped test', async () => {
    const chunks = [];
    const pass = await execute('console.log("complete")', { onOutput: chunk => chunks.push(String(chunk)) });
    assert.equal(pass.code, 0);
    assert.equal(pass.timedOut, false);
    assert.equal(chunks.join(''), pass.output);
    assert.equal((await execute('process.exit(7)')).code, 7);
    assert.equal((await execute('console.log("ℹ skipped 1")')).code, 1);
    const missing = await runVerificationProcess(['/nonexistent/restaurant-migration-command'], { env: process.env, timeout: 500 });
    assert.notEqual(missing.code, 0);
    assert.match(missing.output, /ENOENT/);
});

test('timeout lets an owner clean its separate child session before returning failure', async () => {
    const before = [process.listenerCount('SIGINT'), process.listenerCount('SIGTERM')];
    const result = await execute(`
        const {spawn}=require('node:child_process');
        const child=spawn(process.execPath,['-e','setInterval(()=>{},1000)'],{detached:true,stdio:'ignore'});
        console.log(child.pid);
        process.on('SIGTERM',()=>{ process.kill(-child.pid,'SIGTERM'); child.once('exit',()=>{console.log('cleaned');process.exit(0);}); });
        setInterval(()=>{},1000);
    `, { timeout: 500 });
    assert.equal(result.code, 124);
    assert.equal(result.timedOut, true);
    assert.match(result.output, /cleaned/);
    const childPid = Number(result.output.split('\n')[0]);
    assert.throws(() => process.kill(childPid, 0), { code: 'ESRCH' });
    assert.deepEqual([process.listenerCount('SIGINT'), process.listenerCount('SIGTERM')], before);
});

test('timeout escalates when the owned command ignores graceful termination', async () => {
    const started = Date.now();
    const result = await execute('process.on("SIGTERM",()=>{});setInterval(()=>{},1000)', { timeout: 200, grace: 100 });
    assert.equal(result.code, 124);
    assert.equal(result.timedOut, true);
    assert.ok(Date.now() - started < 3_000);
});
