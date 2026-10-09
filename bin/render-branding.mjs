#!/usr/bin/env node
/** Render presentation-only vector artwork. Requires sharp; never changes the runtime ZIP. */
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const require = createRequire(import.meta.url);
const sharp = require(process.env.QSN_SHARP_MODULE || 'sharp');
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outputs = [
 ['docs/branding/github-social-preview.svg','docs/branding/github-social-preview.png',1280,640],
 ['docs/branding/wordpress-banner.svg','wordpress-org-assets/banner-772x250.png',772,250],
 ['docs/branding/wordpress-banner.svg','wordpress-org-assets/banner-1544x500.png',1544,500],
 ['docs/branding/stocknotifier-mark.svg','wordpress-org-assets/icon-128x128.png',128,128],
 ['docs/branding/stocknotifier-mark.svg','wordpress-org-assets/icon-256x256.png',256,256]
];
for (const [input,output,width,height] of outputs) {
 await sharp(path.join(root,input),{density:288}).resize(width,height).png({compressionLevel:9}).toFile(path.join(root,output));
 console.log(`${output}: ${width} x ${height}`);
}
