// Generate a minimal valid HLS stream for testing
// Creates .m3u8 playlist and minimal .ts segments with a static color frame
import fs from 'fs'
import path from 'path'

const OUTPUT_DIR = 'D:/phpstudy_pro/WWW/douyin/php/public/hls/room'
const ROOM_ID = process.argv[2] || '8'

// Pre-built minimal MPEG-TS segment (H.264 baseline, ~1 sec black frame at 320x240)
// This uses a hand-crafted minimal valid TS container with a single I-frame
function createMinimalTSSegment(frameColor = [0, 0, 0]) {
  // Build a minimal MPEG-TS packet (188 bytes) with PAT
  const tsPacketSize = 188

  // PAT (Program Association Table) - TS packet
  const pat = Buffer.alloc(tsPacketSize)
  pat[0] = 0x47 // Sync byte
  pat[1] = 0x40 // PUSI=1, priority=0, PID high=0
  pat[2] = 0x00 // PID low=0
  pat[3] = 0x10 // No scrambling, AFC=01 (no adaptation field, payload only), CC=0
  // Pointer field
  pat[4] = 0x00
  // PAT table
  pat[5] = 0x00 // Table ID
  pat[6] = 0xB0 // Section syntax indicator=1, private=0, reserved=11, length high
  pat[7] = 0x0D // Section length = 13
  pat[8] = 0x00 // Transport stream ID high
  pat[9] = 0x01 // Transport stream ID low
  pat[10] = 0xC1 // Reserved=11, version=0, current_next=1
  pat[11] = 0x00 // Section number
  pat[12] = 0x00 // Last section number
  pat[13] = 0x00 // Program number high
  pat[14] = 0x01 // Program number low
  pat[15] = 0xF0 // Reserved=111, PID high
  pat[16] = 0x10 // PID = 0x0010 (PMT PID = 16)
  // CRC32 placeholder
  pat[17] = 0x00
  pat[18] = 0x00
  pat[19] = 0x00
  pat[20] = 0x00
  // Fill rest with 0xFF
  for (let i = 21; i < tsPacketSize; i++) pat[i] = 0xFF

  return pat
}

// This approach is getting too complex. Let me use a different strategy:
// Generate HLS using a canvas-like approach - write a simple raw H.264 NAL + mux into TS

// Actually, let me use an even simpler approach:
// Create a valid .m3u8 + generate .ts using PHP's GD library or just serve a
// looped color-bar pattern using raw video data

console.log('Using simple test HLS generator...')

// Create output directory
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true })
}

// The simplest approach: create a valid but minimal HLS playlist
// that any HLS.js player can handle

// Create a minimal valid .m3u8
const m3u8 = [
  '#EXTM3U',
  '#EXT-X-VERSION:3',
  '#EXT-X-TARGETDURATION:2',
  '#EXT-X-MEDIA-SEQUENCE:0',
  '#EXTINF:2.0,',
  `${ROOM_ID}_000.ts`,
  '#EXTINF:2.0,',
  `${ROOM_ID}_001.ts`,
  '#EXTINF:2.0,',
  `${ROOM_ID}_002.ts`,
  '#EXT-X-ENDLIST'
].join('\n') + '\n'

fs.writeFileSync(path.join(OUTPUT_DIR, `${ROOM_ID}.m3u8`), m3u8)

// Create minimal TS files - a single PAT+PMT is enough structure
// These are not playable video but show the HLS pipeline works
// For actual video, use ffmpeg or a pre-made test file

// Minimal TS segment (PAT + PMT only, no video data)
// This won't play but validates the HLS fetching pipeline works
function createEmptyTS() {
  const buf = Buffer.alloc(188 * 3) // 3 packets

  // Packet 1: PAT
  const p = [0x47, 0x40, 0x00, 0x10, 0x00, 0x00, 0xB0, 0x0D, 0x00, 0x01, 0xC1, 0x00, 0x00, 0x00, 0x01, 0xF0, 0x10]
  for (let i = 0; i < p.length; i++) buf[i] = p[i]
  // CRC placeholder
  buf[17] = 0x2E; buf[18] = 0xA0; buf[19] = 0xB2; buf[20] = 0x27
  for (let i = 21; i < 188; i++) buf[i] = 0xFF

  // Packet 2: PMT  
  const offset = 188
  buf[offset] = 0x47; buf[offset+1] = 0x40; buf[offset+2] = 0x10; buf[offset+3] = 0x10
  buf[offset+4] = 0x00; buf[offset+5] = 0x02; buf[offset+6] = 0xB0; buf[offset+7] = 0x12
  buf[offset+8] = 0x00; buf[offset+9] = 0x01; buf[offset+10] = 0xC1; buf[offset+11] = 0x00; buf[offset+12] = 0x00
  buf[offset+13] = 0xE0; buf[offset+14] = 0x10 // PCR PID = video PID
  buf[offset+15] = 0xF0; buf[offset+16] = 0x00 // No descriptors
  // Program info length = 0 (already set at offset+15-16)
  buf[offset+17] = 0x1B; buf[offset+18] = 0xE0; buf[offset+19] = 0x10 // Video stream type=H.264, PID=0x10
  buf[offset+20] = 0xF0; buf[offset+21] = 0x00 // ES info length=0
  for (let i = offset+22; i < offset+188; i++) buf[i] = 0xFF

  // Packet 3: null
  const offset3 = 376
  buf[offset3] = 0x47; buf[offset3+1] = 0x1F; buf[offset3+2] = 0xFF; buf[offset3+3] = 0x10
  for (let i = offset3+4; i < offset3+188; i++) buf[i] = 0xFF

  return buf
}

// Generate 3 segments
for (let i = 0; i < 3; i++) {
  const seg = createEmptyTS()
  fs.writeFileSync(path.join(OUTPUT_DIR, `${ROOM_ID}_00${i}.ts`), seg)
  console.log(`Created ${ROOM_ID}_00${i}.ts (${seg.length} bytes)`)
}

console.log(`\nDone! HLS files ready:`)
console.log(`  ${OUTPUT_DIR}\\${ROOM_ID}.m3u8`)
console.log(`  URL: http://localhost:3000/hls/room/${ROOM_ID}.m3u8`)
console.log(`\nNOTE: These are minimal TS containers (no actual video frames).`)
console.log(`For real video, install ffmpeg and use: npm run start`)
