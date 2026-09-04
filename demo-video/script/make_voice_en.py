# -*- coding: utf-8 -*-
"""สร้างเสียงพากย์อังกฤษ + ซับ จากสคริปต์ presentation_script_en.md

ทำไมต้องมีสคริปต์นี้แทนที่จะสั่ง edge-tts ทีเดียวทั้งฉาก
--------------------------------------------------------
TTS ที่ป้อนข้อความยาว ๆ ทีเดียวจะอ่านรวดเดียวจนฟังออกว่าเป็นเครื่อง เพราะไม่มี
จังหวะหายใจระหว่างประโยค สคริปต์นี้จึงตัดเป็นรายประโยค เรนเดอร์ทีละประโยค แล้ว
ต่อกลับด้วยช่วงเงียบที่คุมความยาวเอง (ท้ายประโยค / ท้ายย่อหน้า / ระหว่างฉาก
ยาวไม่เท่ากัน) ผลที่ได้ฟังเป็นคนพูดจริงมากกว่าเดิมชัดเจน

ผลพลอยได้ที่สำคัญ: พอวัดความยาวของแต่ละประโยคได้จริง ซับ .srt ก็ตรงกับเสียง
เป๊ะโดยไม่ต้องมานั่งเดาเวลา

    python demo-video/script/make_voice_en.py [--voice en-US-AndrewMultilingualNeural]

ต้องมี: pip install edge-tts, และ ffmpeg/ffprobe ใน PATH
"""
import argparse
import re
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
SCRIPT_MD = HERE / 'presentation_script_en.md'
OUT_VOICE = HERE.parent / 'voice' / 'en'
OUT_SRT = HERE.parent / 'subtitles' / 'presentation_en.srt'

# ชื่อไฟล์ต่อฉาก คงชื่อเดิมไว้ เพราะสคริปต์และเอกสารอ้างถึงชื่อพวกนี้อยู่
SCENE_SLUGS = [
    's01_opening', 's02_background', 's03_objectives', 's04_architecture',
    's05_workflow', 's06_features', 's07_duration', 's08_admin',
    's09_dashboard', 's10_roles', 's11_roadmap', 's12_conclusion',
]

GAP_SENTENCE = 0.32   # หายใจสั้น ๆ ระหว่างประโยคในย่อหน้าเดียวกัน
GAP_PARAGRAPH = 0.70  # เว้นให้ความคิดจบก่อนขึ้นย่อหน้าใหม่
GAP_SCENE = 0.95      # ช่วงเปลี่ยนสไลด์ ผู้ฟังต้องมีเวลาเก็บภาพใหม่
SAMPLE_RATE = 24000   # edge-tts ส่งมาที่ 24 kHz mono อยู่แล้ว ใช้ค่าเดียวกันตลอด


def run(cmd):
    subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def duration(path):
    out = subprocess.run(
        ['ffprobe', '-v', 'error', '-show_entries', 'format=duration',
         '-of', 'default=nw=1:nk=1', str(path)],
        check=True, capture_output=True, text=True)
    return float(out.stdout.strip())


def synth(args, sentence, out_mp3):
    """เรียก edge-tts หนึ่งประโยค พร้อมลองใหม่เมื่อบริการสะดุด

    ยิงติดกันหลายร้อยครั้งมีโอกาสเจอ error ชั่วคราวจากฝั่ง Microsoft อยู่เสมอ
    ถ้าไม่ลองใหม่ งานทั้งชุดจะพังกลางคันเพราะประโยคเดียว
    """
    last = None
    for attempt in range(5):
        try:
            subprocess.run(
                [sys.executable, '-m', 'edge_tts', '--voice', args.voice,
                 '--rate=' + args.rate, '--text', sentence,
                 '--write-media', str(out_mp3)],
                check=True, capture_output=True)
            if out_mp3.exists() and out_mp3.stat().st_size > 0:
                return
            last = 'ได้ไฟล์ว่าง'
        except subprocess.CalledProcessError as exc:
            last = (exc.stderr or b'').decode('utf-8', 'replace').strip()[-300:]
        time.sleep(1.5 * (attempt + 1))
    raise RuntimeError('เรนเดอร์ประโยคนี้ไม่สำเร็จหลังลอง 5 ครั้ง: %r\n%s'
                       % (sentence[:80], last))


def parse_scenes(md_text):
    """คืน [(หัวข้อฉาก, [ย่อหน้า[ประโยค]])] ตามลำดับในสคริปต์"""
    scenes = []
    blocks = re.split(r'^## Scene \d+ — ', md_text, flags=re.M)[1:]
    for block in blocks:
        title = block.splitlines()[0].strip()
        m = re.search(r'\*\*Voice-over:\*\*\n(.*?)(?=\n\*\*)', block, re.S)
        if not m:
            continue
        paragraphs = []
        for para in re.split(r'\n\s*\n', m.group(1).strip()):
            # สคริปต์ตัดบรรทัดไว้ให้อ่านง่าย ต้องรวมกลับเป็นย่อหน้าเดียวก่อนตัดประโยค
            text = ' '.join(line.strip() for line in para.splitlines() if line.strip())
            if not text:
                continue
            sentences = [s.strip() for s in re.split(r'(?<=[.!?])\s+', text) if s.strip()]
            if sentences:
                paragraphs.append(sentences)
        scenes.append((title, paragraphs))
    return scenes


def srt_time(seconds):
    ms = int(round(seconds * 1000))
    h, ms = divmod(ms, 3600000)
    m, ms = divmod(ms, 60000)
    s, ms = divmod(ms, 1000)
    return '%02d:%02d:%02d,%03d' % (h, m, s, ms)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--voice', default='en-US-AndrewMultilingualNeural')
    ap.add_argument('--rate', default='-3%')
    ap.add_argument('--scenes', default='', help='เช่น 1,5,12 — อัดใหม่เฉพาะบางฉาก')
    args = ap.parse_args()

    for tool in ('ffmpeg', 'ffprobe'):
        if not shutil.which(tool):
            sys.exit('ไม่พบ %s ใน PATH' % tool)

    scenes = parse_scenes(SCRIPT_MD.read_text(encoding='utf-8'))
    if len(scenes) != len(SCENE_SLUGS):
        sys.exit('สคริปต์มี %d ฉาก แต่คาดไว้ %d' % (len(scenes), len(SCENE_SLUGS)))

    only = set(int(x) for x in args.scenes.split(',') if x.strip()) if args.scenes else None
    OUT_VOICE.mkdir(parents=True, exist_ok=True)
    tmp = Path(tempfile.mkdtemp(prefix='ntc_tts_'))

    # ไฟล์เงียบสำหรับคั่น สร้างครั้งเดียวแล้วใช้ซ้ำ
    silences = {}
    for name, secs in (('sent', GAP_SENTENCE), ('para', GAP_PARAGRAPH), ('scene', GAP_SCENE)):
        p = tmp / ('sil_%s.wav' % name)
        run(['ffmpeg', '-y', '-f', 'lavfi', '-i',
             'anullsrc=r=%d:cl=mono' % SAMPLE_RATE, '-t', str(secs), str(p)])
        silences[name] = p

    full_parts, cues, clock = [], [], 0.0

    for idx, (title, paragraphs) in enumerate(scenes, start=1):
        slug = SCENE_SLUGS[idx - 1]
        scene_mp3 = OUT_VOICE / (slug + '.mp3')
        if only and idx not in only:
            print('ข้ามฉาก %d (%s)' % (idx, slug))
            if scene_mp3.exists():
                clock += duration(scene_mp3) + GAP_SCENE
            continue

        print('ฉาก %d: %s' % (idx, title))
        parts = []
        for pi, sentences in enumerate(paragraphs):
            for si, sentence in enumerate(sentences):
                wav = tmp / ('%s_%02d_%02d.wav' % (slug, pi, si))
                mp3 = wav.with_suffix('.mp3')
                synth(args, sentence, mp3)
                run(['ffmpeg', '-y', '-i', str(mp3), '-ar', str(SAMPLE_RATE),
                     '-ac', '1', str(wav)])
                cues.append([clock, clock + duration(wav), sentence])
                clock += duration(wav)
                parts.append(wav)

                last_in_para = si == len(sentences) - 1
                last_in_scene = last_in_para and pi == len(paragraphs) - 1
                if last_in_scene:
                    continue
                parts.append(silences['para'] if last_in_para else silences['sent'])
                clock += GAP_PARAGRAPH if last_in_para else GAP_SENTENCE

        # ต่อเป็นฉากเดียว แล้วปรับความดังให้เท่ากันทุกฉาก (-16 LUFS แบบมาตรฐานคลิปพูด)
        listfile = tmp / (slug + '.txt')
        listfile.write_text(''.join("file '%s'\n" % p.as_posix() for p in parts), encoding='utf-8')
        run(['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', str(listfile),
             '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11', '-ar', '44100',
             '-b:a', '192k', str(scene_mp3)])
        clock += GAP_SCENE
        full_parts.append(scene_mp3)

    if only:
        print('อัดเฉพาะบางฉาก — ไม่รวมไฟล์เต็มและไม่เขียนซับใหม่')
        return

    # ไฟล์เต็ม: ต่อฉากทั้งหมดโดยคั่นด้วยช่วงเงียบระหว่างสไลด์
    joined = []
    for i, p in enumerate(full_parts):
        wav = tmp / ('scene_%02d.wav' % i)
        run(['ffmpeg', '-y', '-i', str(p), '-ar', str(SAMPLE_RATE), '-ac', '1', str(wav)])
        joined.append(wav)
        if i != len(full_parts) - 1:
            joined.append(silences['scene'])
    listfile = tmp / 'full.txt'
    listfile.write_text(''.join("file '%s'\n" % p.as_posix() for p in joined), encoding='utf-8')
    full_mp3 = OUT_VOICE / 'presentation_full_en.mp3'
    run(['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', str(listfile),
         '-ar', '44100', '-b:a', '192k', str(full_mp3)])

    OUT_SRT.parent.mkdir(parents=True, exist_ok=True)
    with OUT_SRT.open('w', encoding='utf-8') as fh:
        for n, (start, end, text) in enumerate(cues, start=1):
            fh.write('%d\n%s --> %s\n%s\n\n' % (n, srt_time(start), srt_time(end), text))

    # คอนโซล Windows ภาษาไทยเป็น cp874 ซึ่งเข้ารหัส '·' ไม่ได้ ถ้า print ตรง ๆ
    # สคริปต์จะพังตรงบรรทัดสุดท้ายทั้งที่งานเสร็จหมดแล้ว — ตัดอักขระที่เข้ารหัส
    # ไม่ได้ทิ้งไปเงียบ ๆ ดีกว่าเสียงานทั้งชุดเพราะข้อความสรุป
    total = duration(full_mp3)
    summary = ('\nเสร็จ: %s ยาว %d:%02d (%d ประโยค) ซับ %s'
               % (full_mp3.name, total // 60, total % 60, len(cues), OUT_SRT.name))
    enc = sys.stdout.encoding or 'utf-8'
    print(summary.encode(enc, 'replace').decode(enc, 'replace'))
    shutil.rmtree(tmp, ignore_errors=True)


if __name__ == '__main__':
    main()
