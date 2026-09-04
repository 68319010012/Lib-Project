# -*- coding: utf-8 -*-
"""ประกอบสไลด์ + เสียงพากย์ + ซับ เป็นวิดีโอไฟล์เดียว

Canva ใส่ไฟล์เสียงลงสไลด์ผ่าน API ไม่ได้ (ใส่ได้แค่รูปกับวิดีโอ) ทางที่ได้ไฟล์
เดียวจบคือเรนเดอร์เองที่นี่ ข้อดีคือเปิดที่ไหนก็ได้ ไม่ต้องมีเน็ต ไม่ต้องกด
เลื่อนสไลด์ให้ทันเสียง และเอาไปส่งอาจารย์ได้เลย

หัวใจอยู่ที่ความยาวของแต่ละหน้า: ไม่ได้กำหนดตายตัว แต่วัดจากไฟล์เสียงของฉาก
นั้นจริง ๆ บวกช่วงเงียบระหว่างฉากที่ make_voice_en.py ใส่ไว้ ผลรวมจึงเท่ากับ
ความยาวไฟล์เสียงเต็มเป๊ะ ภาพกับเสียงเลยไม่มีทางเลื่อนออกจากกัน

    python demo-video/script/make_video_en.py

ต้องมี: สไลด์ PNG ใน recordings/slides_en/, เสียงจาก make_voice_en.py, ffmpeg
"""
import subprocess
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
SLIDES = ROOT / 'recordings' / 'slides_en'
VOICE = ROOT / 'voice' / 'en'
SRT = ROOT / 'subtitles' / 'presentation_en.srt'
OUT = ROOT / 'output' / 'presentation-en.mp4'

# ต้องตรงกับ GAP_SCENE ใน make_voice_en.py ไม่งั้นภาพจะเลื่อนสะสมทีละฉาก
GAP_SCENE = 0.95

SCENE_SLUGS = [
    's01_opening', 's02_background', 's03_objectives', 's04_architecture',
    's05_workflow', 's06_features', 's07_duration', 's08_admin',
    's09_dashboard', 's10_roles', 's11_roadmap', 's12_conclusion',
]


def duration(path):
    out = subprocess.run(
        ['ffprobe', '-v', 'error', '-show_entries', 'format=duration',
         '-of', 'default=nw=1:nk=1', str(path)],
        check=True, capture_output=True, text=True)
    return float(out.stdout.strip())


def main():
    full_mp3 = VOICE / 'presentation_full_en.mp3'
    for p in (full_mp3, SRT):
        if not p.exists():
            sys.exit('ไม่พบ %s — รัน make_voice_en.py ก่อน' % p)

    slides, holds = [], []
    for i, slug in enumerate(SCENE_SLUGS, start=1):
        png = SLIDES / ('slide%02d.png' % i)
        mp3 = VOICE / (slug + '.mp3')
        if not png.exists():
            sys.exit('ไม่พบสไลด์ %s — export PNG จาก Canva ก่อน' % png.name)
        if not mp3.exists():
            sys.exit('ไม่พบเสียง %s' % mp3.name)
        slides.append(png)
        # หน้าสุดท้ายไม่มีช่วงเงียบต่อท้าย เพราะไม่มีฉากถัดไปให้คั่น
        holds.append(duration(mp3) + (GAP_SCENE if i < len(SCENE_SLUGS) else 0))

    tmp = Path(tempfile.mkdtemp(prefix='ntc_vid_'))
    listfile = tmp / 'slides.txt'
    lines = []
    for png, hold in zip(slides, holds):
        lines.append("file '%s'\nduration %.3f\n" % (png.as_posix(), hold))
    # concat demuxer ตัดเฟรมสุดท้ายทิ้งถ้าไม่ประกาศไฟล์ซ้ำปิดท้าย
    lines.append("file '%s'\n" % slides[-1].as_posix())
    listfile.write_text(''.join(lines), encoding='utf-8')

    # ffmpeg อ่าน path ของซับผ่าน filter จึงต้องหนี ':' ของไดรฟ์ Windows
    # ก๊อปมาไว้ในโฟลเดอร์ชั่วคราวแล้วรันจากตรงนั้นง่ายกว่าการ escape
    srt_local = tmp / 'subs.srt'
    srt_local.write_text(SRT.read_text(encoding='utf-8'), encoding='utf-8')

    OUT.parent.mkdir(parents=True, exist_ok=True)
    print('กำลังเรนเดอร์ %d หน้า รวม %.1f วินาที...' % (len(slides), sum(holds)))
    subprocess.run(
        ['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', str(listfile),
         '-i', str(full_mp3),
         # fps ต้องมาก่อน subtitles: concat ป้อนเข้ามาแค่ 12 เฟรม (เฟรมละหนึ่งสไลด์)
         # ถ้าเผาซับก่อนแปลงเฟรมเรต ทั้งสไลด์จะได้ซับของวินาทีแรกวินาทีเดียวแล้ว
         # ค้างอยู่อย่างนั้นทั้งฉาก — ซึ่งส่วนใหญ่คือ "ไม่มีซับเลย" เพราะช่วงต้น
         # ฉากเป็นช่วงเงียบ ต้องมีเฟรมจริงครบ 25 เฟรม/วินาทีก่อน ซับถึงจะเดินตามเวลา
         # ค่าใน force_style ไม่ใช่พิกเซลบนจอ: .srt ไม่ได้บอกความละเอียดอ้างอิง
         # libass จึงถือว่าเป็น 384x288 แล้วขยายขึ้น 1080p — ทุกค่าโดนคูณ ~3.2 เท่า
         # (ลองใส่ original_size=1920x1080 แล้วยิ่งใหญ่กว่าเดิม จึงคิดกลับทางนี้แทน)
         # FontSize=13 จึงออกมาราว 42px บนจอ 1080p ซึ่งอ่านออกแต่ไม่บังสไลด์
         # MarginV=20 ก็ราว 64px ยกให้พ้นแถบฟุตเตอร์ล่างสุดพอดี
         '-vf', "fps=25,subtitles=subs.srt:force_style='FontName=Arial,"
                "FontSize=13,Bold=1,PrimaryColour=&H00FFFFFF,"
                "OutlineColour=&H40000000,BorderStyle=3,Outline=2,Shadow=0,"
                "MarginV=27',format=yuv420p",
         '-c:v', 'libx264', '-preset', 'medium', '-crf', '20',
         '-c:a', 'aac', '-b:a', '192k', '-shortest', str(OUT)],
        check=True, cwd=str(tmp))

    print('เสร็จ: %s (%.1f วินาที, %.1f MB)'
          % (OUT.name, duration(OUT), OUT.stat().st_size / 1048576))


if __name__ == '__main__':
    main()
