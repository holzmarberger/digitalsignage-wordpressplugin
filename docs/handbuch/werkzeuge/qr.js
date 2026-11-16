// QR-Code (SVG) für den Aushang – mit derselben Bibliothek wie der Player. Aufruf: node qr.js <url> > qr.svg
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'assets', 'vendor', 'qrcode.js'), 'utf8');
const mod = { exports: {} };
new Function('module', 'exports', 'define', src)(mod, mod.exports, undefined);
const qrcode = mod.exports.qrcode || mod.exports;
const url = process.argv[2];
if (!url) { console.error('Aufruf: node qr.js <url>'); process.exit(1); }
const q = qrcode(0, 'M');
q.addData(url);
q.make();
process.stdout.write(q.createSvgTag({ cellSize: 8, margin: 0, scalable: true }));
