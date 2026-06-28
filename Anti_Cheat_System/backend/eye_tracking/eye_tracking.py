import cv2
import numpy as np
import urllib.request
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
MODEL_URL = "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task"
MODEL_PATH = os.path.join(BASE_DIR, "model", "face_landmarker.task")
MIN_MODEL_BYTES = 1_000_000


def _ensure_face_landmarker_model() -> str:
    os.makedirs(os.path.dirname(MODEL_PATH), exist_ok=True)

    if os.path.exists(MODEL_PATH) and os.path.getsize(MODEL_PATH) >= MIN_MODEL_BYTES:
        return MODEL_PATH

    if os.path.exists(MODEL_PATH):
        os.remove(MODEL_PATH)

    print("Downloading MediaPipe face landmarker model...")
    urllib.request.urlretrieve(MODEL_URL, MODEL_PATH)

    if not os.path.exists(MODEL_PATH) or os.path.getsize(MODEL_PATH) < MIN_MODEL_BYTES:
        raise RuntimeError("Failed to download a valid MediaPipe face landmarker model.")

    return MODEL_PATH


_ensure_face_landmarker_model()

import mediapipe as mp
from mediapipe.tasks import python as mp_python
from mediapipe.tasks.python import vision as mp_vision

LEFT_IRIS  = [474, 475, 476, 477]
RIGHT_IRIS = [469, 470, 471, 472]
LEFT_EYE_CORNERS  = [33, 133]
RIGHT_EYE_CORNERS = [362, 263]
NOSE_TIP  = 1
CHIN      = 152
LEFT_EAR  = 234
RIGHT_EAR = 454

GAZE_LEFT_THRESHOLD  = 0.35
GAZE_RIGHT_THRESHOLD = 0.65
YAW_THRESHOLD        = 20
PITCH_DOWN_THRESHOLD = 15

landmarker = mp_vision.FaceLandmarker.create_from_options(
    mp_vision.FaceLandmarkerOptions(
        base_options=mp_python.BaseOptions(model_asset_path=MODEL_PATH),
        num_faces=5,
        min_face_detection_confidence=0.5,
        running_mode=mp_vision.RunningMode.IMAGE
    )
)

def _iris_center(landmarks, indices, w, h):
    pts = [(int(landmarks[i].x * w), int(landmarks[i].y * h)) for i in indices]
    return int(np.mean([p[0] for p in pts])), int(np.mean([p[1] for p in pts]))

def _gaze_ratio(iris_cx, corner_left_x, corner_right_x):
    eye_width = abs(corner_right_x - corner_left_x)
    return 0.5 if eye_width == 0 else (iris_cx - corner_left_x) / eye_width

def _head_pose(landmarks, w, h):
    def pt(idx): return np.array([landmarks[idx].x * w, landmarks[idx].y * h])
    nose = pt(NOSE_TIP); left_ear = pt(LEFT_EAR); right_ear = pt(RIGHT_EAR); chin = pt(CHIN)
    left_dist  = abs(nose[0] - left_ear[0])
    right_dist = abs(nose[0] - right_ear[0])
    total = left_dist + right_dist
    yaw   = ((right_dist - left_dist) / total) * 100 if total > 0 else 0
    face_height = abs(chin[1] - nose[1])
    mid_y = (left_ear[1] + right_ear[1]) / 2
    pitch = ((nose[1] - mid_y) / face_height) * 100 if face_height > 0 else 0
    return yaw, pitch

def analyze_gaze(image_bytes: bytes) -> dict:
    nparr = np.frombuffer(image_bytes, np.uint8)
    frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if frame is None:
        return {"face_count": 0, "gaze_direction": "ERROR", "is_cheating": False, "cheat_reason": "invalid_image", "details": {}}

    h, w = frame.shape[:2]
    rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb)
    result = landmarker.detect(mp_image)

    if not result.face_landmarks:
        return {"face_count": 0, "gaze_direction": "NO_FACE", "is_cheating": True, "cheat_reason": "no_face_detected", "details": {}}

    face_count = len(result.face_landmarks)
    if face_count > 1:
        return {"face_count": face_count, "gaze_direction": "UNKNOWN", "is_cheating": True, "cheat_reason": "multiple_faces_detected", "details": {}}

    landmarks = result.face_landmarks[0]

    left_iris_cx,  _ = _iris_center(landmarks, LEFT_IRIS,  w, h)
    right_iris_cx, _ = _iris_center(landmarks, RIGHT_IRIS, w, h)

    l_corner_l = int(landmarks[LEFT_EYE_CORNERS[0]].x  * w)
    l_corner_r = int(landmarks[LEFT_EYE_CORNERS[1]].x  * w)
    r_corner_l = int(landmarks[RIGHT_EYE_CORNERS[0]].x * w)
    r_corner_r = int(landmarks[RIGHT_EYE_CORNERS[1]].x * w)

    ratio_left  = _gaze_ratio(left_iris_cx,  l_corner_l, l_corner_r)
    ratio_right = _gaze_ratio(right_iris_cx, r_corner_l, r_corner_r)
    avg_ratio   = (ratio_left + ratio_right) / 2
    yaw, pitch  = _head_pose(landmarks, w, h)

    gaze_direction = "CENTER"
    if pitch > PITCH_DOWN_THRESHOLD:
        gaze_direction = "DOWN"
    elif yaw > YAW_THRESHOLD or avg_ratio > GAZE_RIGHT_THRESHOLD:
        gaze_direction = "RIGHT"
    elif yaw < -YAW_THRESHOLD or avg_ratio < GAZE_LEFT_THRESHOLD:
        gaze_direction = "LEFT"

    is_cheating  = gaze_direction != "CENTER"
    cheat_reason = None
    if gaze_direction == "DOWN":
        cheat_reason = "looking_at_phone_or_paper"
    elif gaze_direction in ("LEFT", "RIGHT"):
        cheat_reason = "looking_away_from_screen"

    return {
        "face_count"     : face_count,
        "gaze_direction" : gaze_direction,
        "is_cheating"    : is_cheating,
        "cheat_reason"   : cheat_reason,
        "details": {
            "gaze_ratio_left" : round(ratio_left,  3),
            "gaze_ratio_right": round(ratio_right, 3),
            "yaw"             : round(yaw,   2),
            "pitch"           : round(pitch, 2)
        }
    }
