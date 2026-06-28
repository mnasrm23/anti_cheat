from ultralytics import YOLO
import os
import tempfile

import os
from ultralytics import YOLO

BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
MODEL_PATH = os.path.join(BASE_DIR, "model", "best.pt")

print("YOLO model path:", MODEL_PATH)

model = YOLO(MODEL_PATH)


def detect_eye_from_image(image_bytes: bytes):
    with tempfile.NamedTemporaryFile(delete=False, suffix=".jpg") as f:
        f.write(image_bytes)
        img_path = f.name

    try:
        results = model(img_path, conf=0.4)
        boxes = results[0].boxes
        eye_count = 0 if boxes is None else len(boxes)

        return {
            "eye_detected": eye_count > 0,
            "eye_count": eye_count
        }
    finally:
        os.remove(img_path)
