import json
import urllib.request
import urllib.error

API_URL = "http://localhost:8080/moto_dashboard/api/detection.php"
API_KEY = "MOTO_AI_KEY_2026"

# Dữ liệu mẫu giả lập kết quả từ mô hình YOLO + OCR biển số
payload = {
    "camera_id": 1,               # 1 = Cổng chính (Cổng A), 2 = Cổng phụ (Cổng B)
    "track_id": 505,
    "license_plate": "65C1-889.12", # Biển số Giảng viên đã đăng ký sẵn trong CSDL
    "has_helmet": 1,              # 1 = Có đội mũ, 0 = Không đội mũ
    "direction": "Vào",           # "Vào" hoặc "Ra"
    "confidence": 0.97,
    "x": 28,
    "y": 24,
    "width": 26,
    "height": 44
    # Có thể kèm "image_base64": "<chuỗi ảnh chụp jpg base64>" nếu cần lưu ảnh thật
}

req = urllib.request.Request(
    API_URL,
    data=json.dumps(payload).encode("utf-8"),
    headers={
        "Content-Type": "application/json",
        "X-API-KEY": API_KEY
    },
    method="POST"
)

try:
    with urllib.request.urlopen(req, timeout=5) as resp:
        body = resp.read().decode("utf-8")
        print("Trang thai HTTP:", resp.status)
        print("Phan hoi tu Dashboard:", body)
except urllib.error.URLError as e:
    print("Loi ket noi API:", e)