"""
سكريبت اختبار كامل للـ Anti-Cheat System
شغله من root الـ project:
    python test_system.py
"""

import sys
import numpy as np
import cv2

# ═══════════════════════════════════════════════════════
#  Helper
# ═══════════════════════════════════════════════════════
def ok(msg):   print(f"  ✅ {msg}")
def fail(msg): print(f"  ❌ {msg}"); sys.exit(1)
def section(title): print(f"\n{'═'*50}\n  {title}\n{'═'*50}")


def make_image_bytes(h=480, w=640):
    """بتعمل صورة وهمية (فيها وجه من الكاميرا) وترجعها bytes"""
    # محاولة تشغيل الكاميرا للحصول على صورة حقيقية
    cap = cv2.VideoCapture(0)
    if cap.isOpened():
        ret, frame = cap.read()
        cap.release()
        if ret:
            _, buf = cv2.imencode(".jpg", frame)
            print("    (صورة حقيقية من الكاميرا)")
            return buf.tobytes()

    # لو مفيش كاميرا → صورة وهمية
    print("    (صورة وهمية - مفيش كاميرا)")
    img = np.zeros((h, w, 3), dtype=np.uint8)
    img[:] = (200, 180, 160)  # لون بشرة تقريبي
    _, buf = cv2.imencode(".jpg", img)
    return buf.tobytes()


# ═══════════════════════════════════════════════════════
#  Test 1: المكتبات
# ═══════════════════════════════════════════════════════
section("1️⃣  اختبار المكتبات")

try:
    import mediapipe
    ok(f"mediapipe {mediapipe.__version__}")
except ImportError:
    fail("mediapipe مش متثبتة — شغل: pip install mediapipe")

try:
    import cv2
    ok(f"opencv {cv2.__version__}")
except ImportError:
    fail("opencv مش متثبتة — شغل: pip install opencv-python")

try:
    from ultralytics import YOLO
    ok("ultralytics (YOLO) موجودة")
except ImportError:
    fail("ultralytics مش متثبتة — شغل: pip install ultralytics")


# ═══════════════════════════════════════════════════════
#  Test 2: YOLO Model
# ═══════════════════════════════════════════════════════
section("2️⃣  اختبار YOLO Model")

try:
    from backend.eye_detection.eye_detection import detect_eye_from_image
    ok("استيراد eye_detection.py نجح")
except Exception as e:
    fail(f"فشل استيراد eye_detection.py: {e}")

try:
    img_bytes = make_image_bytes()
    result = detect_eye_from_image(img_bytes)
    ok(f"YOLO شغال → {result}")
except Exception as e:
    fail(f"YOLO فشل: {e}")


# ═══════════════════════════════════════════════════════
#  Test 3: MediaPipe Eye Tracking
# ═══════════════════════════════════════════════════════
section("3️⃣  اختبار MediaPipe Eye Tracking")

try:
    from backend.eye_tracking.eye_tracking import analyze_gaze
    ok("استيراد eye_tracking.py نجح")
except Exception as e:
    fail(f"فشل استيراد eye_tracking.py: {e}")

try:
    img_bytes = make_image_bytes()
    result = analyze_gaze(img_bytes)
    ok(f"MediaPipe شغال → gaze: {result['gaze_direction']} | faces: {result['face_count']}")
    print(f"    details: {result['details']}")
except Exception as e:
    fail(f"MediaPipe فشل: {e}")


# ═══════════════════════════════════════════════════════
#  Test 4: Session Logic
# ═══════════════════════════════════════════════════════
section("4️⃣  اختبار Session Logic")

try:
    from backend.core.session import update_session
    ok("استيراد session.py نجح")
except Exception as e:
    fail(f"فشل استيراد session.py: {e}")

# اختبار حالة OK
s = update_session("test_001", is_cheating=False)
assert s["status"] == "OK", "فشل: المفروض يكون OK"
ok("حالة OK شغالة")

# اختبار حالة WATCHING (غش بس في الـ grace period)
s = update_session("test_001", is_cheating=True, cheat_reason="looking_away_from_screen")
assert s["status"] == "WATCHING", f"فشل: المفروض WATCHING مش {s['status']}"
ok("حالة WATCHING شغالة")

# اختبار WARNING (بعد 3 ثواني)
import time
update_session("test_002", is_cheating=False)               # بدأ تمام
time.sleep(0.1)
# نخدع العداد بتعديل last_ok مباشرة
from backend.core import session as sess_module
sess_module.sessions["test_002"]["last_ok"] -= 4            # نرجع 4 ثواني للخلف
s = update_session("test_002", is_cheating=True, cheat_reason="no_face_detected")
assert "WARNING" in s["status"] or s["status"] == "TERMINATED", f"فشل: {s['status']}"
ok(f"حالة WARNING/TERMINATE شغالة → {s['status']}")

# اختبار TERMINATE
sess_module.sessions["test_003"] = {
    "last_ok": time.time() - 10,   # 10 ثواني فات
    "warnings": 0,
    "status": "OK",
    "cheat_reason": None,
    "cheat_log": []
}
s = update_session("test_003", is_cheating=True, cheat_reason="multiple_faces_detected")
assert s["status"] == "TERMINATED", f"فشل: المفروض TERMINATED مش {s['status']}"
ok("حالة TERMINATED شغالة")


# ═══════════════════════════════════════════════════════
#  Test 5: FastAPI endpoint (بدون سيرفر)
# ═══════════════════════════════════════════════════════
section("5️⃣  اختبار FastAPI Import")

try:
    from backend.api.main import app
    ok("FastAPI app اتعمل بنجاح")
except Exception as e:
    fail(f"FastAPI فشل: {e}")


# ═══════════════════════════════════════════════════════
#  النتيجة النهائية
# ═══════════════════════════════════════════════════════
print(f"\n{'═'*50}")
print("  🎉 كل الاختبارات عدت! النظام شغال تمام.")
print(f"{'═'*50}\n")
