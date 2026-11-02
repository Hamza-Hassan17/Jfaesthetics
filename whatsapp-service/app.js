// Keeps one WhatsApp linked-device session open and sends messages for the
// JF app. Sends only: incoming messages are ignored and never stored.
//
// Env: WHATSAPP_TOKEN or a .token file (required, same value as the Laravel
// .env), PORT.
// CommonJS entry because cPanel's Node runner can't start an ES module;
// Baileys itself is ESM, hence the dynamic import.
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const QRCode = require('qrcode');
const pino = require('pino');

const AUTH_DIR = path.join(__dirname, 'auth');
const TOKEN_FILE = path.join(__dirname, '.token');

// cPanel's Node screen doesn't always pass its variables to the app, so a
// private .token file next to this script works too.
const TOKEN = process.env.WHATSAPP_TOKEN
    || (fs.existsSync(TOKEN_FILE) ? fs.readFileSync(TOKEN_FILE, 'utf8').trim() : '');

let sock = null;
let state = 'starting'; // starting | qr | connected | disconnected
let qrImage = null;
let number = null;
let retryTimer = null;

async function connect() {
    clearTimeout(retryTimer);
    const { default: makeWASocket, useMultiFileAuthState, fetchLatestBaileysVersion, DisconnectReason } = await import('baileys');
    const { state: auth, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version } = await fetchLatestBaileysVersion();

    const socket = makeWASocket({
        version,
        auth,
        logger: pino({ level: 'silent' }),
        markOnlineOnConnect: false,
        syncFullHistory: false,
    });
    sock = socket;

    socket.ev.on('creds.update', saveCreds);
    socket.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
        if (socket !== sock) return; // replaced by a newer connection

        if (qr) {
            state = 'qr';
            qrImage = await QRCode.toDataURL(qr, { width: 320, margin: 1 });
        }
        if (connection === 'open') {
            state = 'connected';
            qrImage = null;
            number = (socket.user && socket.user.id || '').split(/[:@]/)[0] || null;
            console.log('connected as', number);
        }
        if (connection === 'close') {
            const code = lastDisconnect && lastDisconnect.error && lastDisconnect.error.output && lastDisconnect.error.output.statusCode;
            state = 'disconnected';
            number = null;
            qrImage = null;
            console.log('connection closed, code', code);
            if (code === DisconnectReason.loggedOut) {
                fs.rmSync(AUTH_DIR, { recursive: true, force: true }); // start over with a new QR
            }
            retryTimer = setTimeout(() => connect().catch(onConnectError), 5000);
        }
    });
}

function onConnectError(error) {
    console.error('connect failed:', error.message);
    state = 'disconnected';
    retryTimer = setTimeout(() => connect().catch(onConnectError), 15000);
}

function reply(res, code, body) {
    res.writeHead(code, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(body));
}

function authorised(req) {
    const given = Buffer.from(String(req.headers['x-token'] || ''));
    const expected = Buffer.from(TOKEN);
    return given.length === expected.length && crypto.timingSafeEqual(given, expected);
}

function readJson(req) {
    return new Promise((resolve, reject) => {
        let raw = '';
        req.on('data', (chunk) => {
            raw += chunk;
            if (raw.length > 64 * 1024) { reject(new Error('too large')); req.destroy(); }
        });
        req.on('end', () => {
            try { resolve(raw ? JSON.parse(raw) : {}); } catch (e) { reject(e); }
        });
    });
}

const server = http.createServer(async (req, res) => {
    const route = req.url.split('?')[0].replace(/\/+$/, '').split('/').pop();

    // Open: only wakes the app from cron, reveals nothing.
    if (route === 'ping') return reply(res, 200, { ok: true });
    if (!authorised(req)) return reply(res, 401, { error: 'unauthorised' });

    try {
        if (req.method === 'GET' && route === 'status') {
            return reply(res, 200, { state, number, qr: qrImage });
        }
        if (req.method === 'POST' && route === 'send') {
            const { to, text } = await readJson(req);
            if (state !== 'connected') return reply(res, 409, { error: 'WhatsApp is not connected' });
            if (!/^\d{10,15}$/.test(String(to)) || !text) return reply(res, 422, { error: 'to and text are required' });

            const jid = `${to}@s.whatsapp.net`;
            const [found] = await sock.onWhatsApp(jid);
            if (!found || !found.exists) return reply(res, 422, { error: 'This number is not on WhatsApp' });

            const sent = await sock.sendMessage(found.jid, { text: String(text) });
            return reply(res, 200, { ok: true, id: sent.key.id });
        }
        if (req.method === 'POST' && route === 'logout') {
            if (state === 'connected') await sock.logout(); // close event restarts with a new QR
            return reply(res, 200, { ok: true });
        }
        return reply(res, 404, { error: 'not found' });
    } catch (error) {
        console.error(route, 'failed:', error.message);
        return reply(res, 500, { error: error.message });
    }
});

if (TOKEN.length < 32) {
    console.error('WHATSAPP_TOKEN or a .token file must be set (at least 32 characters).');
    process.exit(1);
}

server.listen(process.env.PORT || 3100, () => {
    console.log('whatsapp service listening');
    connect().catch(onConnectError);
});
