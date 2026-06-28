from fastapi import FastAPI, UploadFile, File, Form
from backend.eye_detection.eye_detection import detect_eye_from_image
from backend.eye_tracking.eye_tracking import analyze_gaze
from backend.core.session import update_session

app = FastAPI(title="Anti Cheat AI")


@app.get("/health")
async def health():
    return {"status": "ok", "service": "Anti Cheat AI"}


@app.post("/frame")
async def process_frame(
    session_id: str = Form(...),
    image: UploadFile = File(...)
):
    img_bytes = await image.read()

    # ── Step 1: YOLO (هل في عين أصلاً؟) ──────────────────────────────────────
    yolo_result = detect_eye_from_image(img_bytes)

    # ── Step 2: MediaPipe (بيبص فين؟ في كام وجه؟) ────────────────────────────
    gaze_result = analyze_gaze(img_bytes)

    # ── Step 3: دمج النتيجتين عشان نقرر في غش ولا لأ ─────────────────────────
    # لو YOLO مش شايف عين أو MediaPipe شايف غش → ده غش
    eye_present   = yolo_result["eye_detected"]
    gaze_cheating = gaze_result["is_cheating"]

    is_cheating = (not eye_present) or gaze_cheating

    cheat_reason = None
    if not eye_present:
        cheat_reason = "no_eye_detected"
    elif gaze_cheating:
        cheat_reason = gaze_result["cheat_reason"]

    # ── Step 4: تحديث الـ Session ─────────────────────────────────────────────
    logic = update_session(session_id, is_cheating, cheat_reason)

    return {
        "yolo"       : yolo_result,
        "gaze"       : gaze_result,
        "anti_cheat" : logic
    }
