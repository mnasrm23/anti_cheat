import cv2
import sys
sys.path.insert(0, '.')
from backend.eye_tracking.eye_tracking import analyze_gaze

print("جاري فتح الكاميرا... اضغط Q للخروج")

cap = cv2.VideoCapture(0)

if not cap.isOpened():
    print("مش قادر يفتح الكاميرا!")
    sys.exit(1)

while True:
    ret, frame = cap.read()
    if not ret:
        break

    # تحويل الفريم لـ bytes وبعتهولو
    _, buf = cv2.imencode(".jpg", frame)
    result = analyze_gaze(buf.tobytes())

    # اختيار لون الـ box حسب الحالة
    color = (0, 255, 0) if not result["is_cheating"] else (0, 0, 255)

    # عرض النتيجة على الشاشة
    gaze  = result["gaze_direction"]
    faces = result["face_count"]
    reason = result["cheat_reason"] or ""

    cv2.putText(frame, f"Gaze: {gaze}",        (20, 40),  cv2.FONT_HERSHEY_SIMPLEX, 1,   color, 2)
    cv2.putText(frame, f"Faces: {faces}",       (20, 80),  cv2.FONT_HERSHEY_SIMPLEX, 0.8, color, 2)
    cv2.putText(frame, f"Reason: {reason}",     (20, 115), cv2.FONT_HERSHEY_SIMPLEX, 0.7, color, 2)

    if result["details"]:
        d = result["details"]
        cv2.putText(frame, f"Yaw: {d['yaw']}  Pitch: {d['pitch']}", (20, 150), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255,255,0), 1)
        cv2.putText(frame, f"Ratio L: {d['gaze_ratio_left']}  R: {d['gaze_ratio_right']}", (20, 175), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255,255,0), 1)

    # border احمر لو غش
    if result["is_cheating"]:
        cv2.rectangle(frame, (0,0), (frame.shape[1]-1, frame.shape[0]-1), (0,0,255), 4)

    cv2.imshow("Anti-Cheat Test", frame)

    # طباعة في الـ terminal برضو
    print(f"Gaze: {gaze:8s} | Faces: {faces} | Cheating: {result['is_cheating']} | {reason}")

    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()
