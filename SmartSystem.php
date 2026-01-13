<?php
// SmartSystem.php
class SmartSystem {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function postJson($url, array $payload, array $headers = []) {
        $ch = curl_init($url);
        $allHeaders = array_merge(['Content-Type: application/json'], $headers);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'error' => $error,
            'body' => $response,
        ];
    }

    // تحليل الهوية (OCR)
    public function analyzeIDCard($imagePath) {
        if (!OCR_API_URL || !OCR_API_KEY) {
            return [
                'success' => false,
                'error' => 'OCR غير مُعد. تأكد من ضبط OCR_API_URL و OCR_API_KEY.',
            ];
        }

        if (!is_file($imagePath)) {
            return [
                'success' => false,
                'error' => 'مسار الصورة غير صالح.',
            ];
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $payload = [
            'image' => $imageData,
        ];

        $result = $this->postJson(OCR_API_URL, $payload, [
            'Authorization: Bearer '.OCR_API_KEY,
        ]);

        if ($result['error'] || $result['status'] >= 400) {
            return [
                'success' => false,
                'error' => 'فشل استدعاء خدمة OCR.',
                'details' => $result['error'] ?: $result['body'],
            ];
        }

        $data = json_decode($result['body'], true);
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    // إرسال واتساب (تسجيل في activity_log المرفق)
    public function sendWhatsApp($phone, $message) {
        if (!WHATSAPP_TOKEN) {
            throw new RuntimeException('WHATSAPP_TOKEN غير مُعد.');
        }

        $payload = [
            'token' => WHATSAPP_TOKEN,
            'to' => $phone,
            'body' => $message,
        ];

        $result = $this->postJson(WHATSAPP_API_URL, $payload);
        if ($result['error'] || $result['status'] >= 400) {
            throw new RuntimeException('فشل إرسال رسالة واتساب.');
        }

        $stmt = $this->pdo->prepare("INSERT INTO activity_log (description, type) VALUES (?, 'whatsapp_sent')");
        $stmt->execute(["تم إرسال رسالة لـ $phone: $message"]);
    }

    public function analyzeMaintenance(string $description, ?float $cost = null): array {
        $text = mb_strtolower(trim($description), 'UTF-8');
        $priority = 'medium';
        $signals = [];

        $emergencyKeywords = ['حريق', 'تماس', 'كهرباء', 'انفجار', 'تسرب كبير', 'ماس كهربائي'];
        $highKeywords = ['تسرب', 'انقطاع', 'عطل', 'سقف', 'ماء', 'تصريف', 'مكيف'];
        foreach ($emergencyKeywords as $word) {
            if (mb_strpos($text, $word) !== false) {
                $priority = 'emergency';
                $signals[] = "كلمة مفتاحية: $word";
                break;
            }
        }
        if ($priority !== 'emergency') {
            foreach ($highKeywords as $word) {
                if (mb_strpos($text, $word) !== false) {
                    $priority = 'high';
                    $signals[] = "كلمة مفتاحية: $word";
                    break;
                }
            }
        }

        if ($cost !== null && $cost >= 5000 && $priority === 'medium') {
            $priority = 'high';
            $signals[] = 'تكلفة مرتفعة';
        }

        if ($priority === 'medium' && $cost !== null && $cost <= 500) {
            $priority = 'low';
            $signals[] = 'تكلفة منخفضة';
        }

        $analysis = $signals
            ? 'تحليل ذكي: '.$priority.' بناءً على '.implode('، ', $signals)
            : 'تحليل ذكي: حالة قياسية تتطلب متابعة دورية.';

        return [
            'priority' => $priority,
            'analysis' => $analysis,
        ];
    }
}
$AI = new SmartSystem($pdo);
?>
